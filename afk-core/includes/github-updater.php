<?php
/**
 * Updates über GitHub-Releases.
 *
 * Fragt die Releases eines öffentlichen GitHub-Repositorys ab und meldet neue Versionen im
 * Dashboard wie bei jedem anderen Plugin oder Theme („Jetzt aktualisieren“).
 *
 * Erwartete Releases: Tag „<slug>-<version>“ (z. B. „afk-core-0.5.0“) mit der Datei
 * „<slug>.zip“ als Anhang. Die GitHub Action im Repository legt diese automatisch an.
 *
 * Dieselbe Datei liegt in Plugin und Theme; geladen wird die Klasse nur einmal.
 *
 * Repository ändern: in wp-config.php  define( 'AFK_UPDATE_REPO', 'besitzer/repository' );
 * Updates abschalten: define( 'AFK_UPDATE_REPO', '' );
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AFK_GitHub_Updater' ) ) {

	/**
	 * Update-Prüfer für ein Plugin oder Theme.
	 */
	class AFK_GitHub_Updater {

		const STANDARD_REPO = 'juliantrng/afk0205-website';
		const CACHE_STUNDEN = 6;

		/** @var string 'plugin' oder 'theme' */
		private $typ;
		/** @var string Ordnername, z. B. afk-core */
		private $slug;
		/** @var string Plugin: afk-core/afk-core.php, Theme: afk-nachtschicht */
		private $datei;
		/** @var string Installierte Version */
		private $version;
		/** @var string Anzeigename */
		private $name;
		/** @var array Ergebnis der letzten Abfragen (für die Statuszeile). */
		private $protokoll = array();

		/**
		 * @param array $args typ, slug, datei, version, name.
		 */
		public function __construct( array $args ) {
			$this->typ     = $args['typ'];
			$this->slug    = $args['slug'];
			$this->datei   = $args['datei'];
			$this->version = $args['version'];
			$this->name    = $args['name'];

			if ( '' === self::repo() ) {
				return;
			}
			// Beim Speichern UND beim Lesen der Update-Liste eintragen, jeweils ganz zum Schluss:
			// manche Plugins mit eigenem Update-Mechanismus ersetzen die Liste und verwerfen fremde Einträge.
			if ( 'plugin' === $this->typ ) {
				add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'pruefen' ), 999 );
				add_filter( 'site_transient_update_plugins', array( $this, 'pruefen' ), 999 );
				add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
			} else {
				add_filter( 'pre_set_site_transient_update_themes', array( $this, 'pruefen' ), 999 );
				add_filter( 'site_transient_update_themes', array( $this, 'pruefen' ), 999 );
			}
			add_action( 'upgrader_process_complete', array( $this, 'cache_leeren' ), 10, 0 );
			if ( 'plugin' === $this->typ ) {
				add_filter( 'plugin_row_meta', array( $this, 'status_zeile' ), 10, 2 );
			}
		}

		/**
		 * Plugins-Liste: wann und wie zuletzt nach Updates gesucht wurde.
		 *
		 * @param array  $meta Links unter der Beschreibung.
		 * @param string $file Plugin-Datei.
		 * @return array
		 */
		public function status_zeile( $meta, $file ) {
			if ( $file !== $this->datei ) {
				return $meta;
			}
			$st = get_site_option( 'afk_gh_status', array() );
			if ( ! empty( $st[ $this->slug ] ) ) {
				$s      = $st[ $this->slug ];
				$meta[] = esc_html(
					sprintf(
						'Update-Prüfung %s: %s',
						wp_date( 'j.n. H:i', (int) $s['zeit'] ),
						$s['version'] ? 'neueste Version ' . $s['version'] . ' (' . $s['weg'] . ')' : 'GitHub nicht erreichbar'
					) . ( $s['fehler'] ? ' – ' . $s['fehler'] : '' )
				);
			}
			return $meta;
		}

		/**
		 * Ergebnis der Suche merken.
		 *
		 * @param string $weg     api|feed|datei|–.
		 * @param string $version Gefundene Version.
		 */
		private function status_merken( $weg, $version ) {
			$st                = get_site_option( 'afk_gh_status', array() );
			$st[ $this->slug ] = array(
				'zeit'    => time(),
				'weg'     => array( 'api' => 'GitHub-API', 'feed' => 'Release-Feed', 'datei' => 'Versionsdatei' )[ $weg ] ?? $weg,
				'version' => $version,
				'fehler'  => implode( ', ', $this->protokoll ),
			);
			update_site_option( 'afk_gh_status', $st );
		}

		/**
		 * Repository „besitzer/name“ oder '' (aus).
		 *
		 * @return string
		 */
		public static function repo() {
			$repo = defined( 'AFK_UPDATE_REPO' ) ? (string) AFK_UPDATE_REPO : self::STANDARD_REPO;
			$repo = (string) apply_filters( 'afk_update_repo', $repo );
			return preg_match( '#^[\w.-]+/[\w.-]+$#', $repo ) ? $repo : '';
		}

		/**
		 * Neuestes Release dieses Bausteins (zwischengespeichert).
		 *
		 * @return array|null version, package, url, notes, datum
		 */
		public function neuestes() {
			$key = 'afk_gh_' . $this->slug;
			// „Erneut prüfen“ unter Dashboard → Aktualisierungen umgeht den Zwischenspeicher.
			static $geleert = array();
			if ( is_admin() && isset( $_GET['force-check'] ) && empty( $geleert[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				delete_site_transient( $key ); // einmal pro Aufruf
				$geleert[ $key ] = true;
			}
			$cache = get_site_transient( $key );
			if ( is_array( $cache ) ) {
				return empty( $cache['version'] ) ? null : $cache;
			}

			$this->protokoll = array();
			$weg             = 'api';
			$releases        = $this->releases_api();
			if ( null === $releases ) {
				// API nicht erreichbar oder Limit erreicht (bei geteilten Server-Adressen häufig):
				// Release-Feed von github.com hat keine solche Grenze.
				$weg      = 'feed';
				$releases = $this->releases_feed();
			}
			if ( null === $releases ) {
				// Letzter Weg: Versionsangabe direkt aus der Datei im Repository (raw.githubusercontent.com).
				$weg      = 'datei';
				$releases = $this->releases_datei();
			}
			if ( null === $releases ) {
				// Alles fehlgeschlagen: eine Stunde warten statt bei jedem Aufruf neu fragen.
				set_site_transient( $key, array(), HOUR_IN_SECONDS );
				$this->status_merken( '–', '' );
				return null;
			}

			$treffer = array();
			foreach ( $releases as $rel ) {
				if ( ! empty( $rel['draft'] ) || ! empty( $rel['prerelease'] ) ) {
					continue;
				}
				$tag = (string) ( $rel['tag_name'] ?? '' );
				if ( 0 !== strpos( $tag, $this->slug . '-' ) ) {
					continue;
				}
				$version = substr( $tag, strlen( $this->slug ) + 1 );
				if ( ! preg_match( '/^\d+(\.\d+)*$/', $version ) ) {
					continue;
				}
				$paket = '';
				foreach ( (array) ( $rel['assets'] ?? array() ) as $asset ) {
					if ( ( $asset['name'] ?? '' ) === $this->slug . '.zip' ) {
						$paket = (string) $asset['browser_download_url'];
					}
				}
				if ( '' === $paket ) {
					continue;
				}
				if ( ! $treffer || version_compare( $version, $treffer['version'], '>' ) ) {
					$treffer = array(
						'version' => $version,
						'package' => $paket,
						'url'     => (string) ( $rel['html_url'] ?? '' ),
						'notes'   => (string) ( $rel['body'] ?? '' ),
						'datum'   => (string) ( $rel['published_at'] ?? '' ),
					);
				}
			}
			set_site_transient( $key, $treffer, self::CACHE_STUNDEN * HOUR_IN_SECONDS );
			$this->status_merken( $weg, $treffer ? $treffer['version'] : '' );
			return $treffer ? $treffer : null;
		}

		/**
		 * Releases über die GitHub-API.
		 *
		 * @return array|null
		 */
		private function releases_api() {
			$res = wp_remote_get(
				'https://api.github.com/repos/' . self::repo() . '/releases?per_page=30',
				array(
					'timeout' => 10,
					'headers' => array( 'Accept' => 'application/vnd.github+json' ),
				)
			);
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				$this->protokoll[] = 'API: ' . ( is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
				return null;
			}
			$json = json_decode( wp_remote_retrieve_body( $res ), true );
			return is_array( $json ) ? $json : null;
		}

		/**
		 * Releases über den Atom-Feed (https://github.com/<repo>/releases.atom), ohne Abfragelimit.
		 * Liefert dieselbe Form wie die API; die ZIP-Adresse folgt dem festen Muster der Releases.
		 *
		 * @return array|null
		 */
		private function releases_feed() {
			$res = wp_remote_get( 'https://github.com/' . self::repo() . '/releases.atom', array( 'timeout' => 10 ) );
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				$this->protokoll[] = 'Feed: ' . ( is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
				return null;
			}
			$xml = wp_remote_retrieve_body( $res );
			if ( ! preg_match_all( '#<entry>(.*?)</entry>#s', $xml, $eintraege ) ) {
				return array();
			}
			$out = array();
			foreach ( $eintraege[1] as $e ) {
				if ( ! preg_match( '#/releases/tag/([^"/<>]+)"#', $e, $tag ) ) {
					continue;
				}
				$tag   = rawurldecode( $tag[1] );
				$datum = preg_match( '#<updated>([^<]+)</updated>#', $e, $u ) ? $u[1] : '';
				$text  = preg_match( '#<content[^>]*>(.*?)</content>#s', $e, $c ) ? wp_strip_all_tags( html_entity_decode( $c[1], ENT_QUOTES, 'UTF-8' ) ) : '';
				$basis = 'https://github.com/' . self::repo() . '/releases';
				$out[] = array(
					'tag_name'     => $tag,
					'draft'        => false,
					'prerelease'   => false,
					'html_url'     => $basis . '/tag/' . rawurlencode( $tag ),
					'published_at' => $datum,
					'body'         => trim( $text ),
					'assets'       => array(
						array(
							'name'                 => $this->slug . '.zip',
							'browser_download_url' => $basis . '/download/' . rawurlencode( $tag ) . '/' . $this->slug . '.zip',
						),
					),
				);
			}
			return $out;
		}

		/**
		 * Version aus der Hauptdatei im Repository lesen (Plugin-Kopf bzw. style.css) und daraus ein
		 * Release in derselben Form bauen. raw.githubusercontent.com hat kein Abfragelimit.
		 *
		 * @return array|null
		 */
		private function releases_datei() {
			$datei = 'plugin' === $this->typ ? $this->datei : $this->slug . '/style.css';
			$zweig = (string) apply_filters( 'afk_update_zweig', 'main' );
			$res   = wp_remote_get( 'https://raw.githubusercontent.com/' . self::repo() . '/' . rawurlencode( $zweig ) . '/' . $datei, array( 'timeout' => 10 ) );
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				$this->protokoll[] = 'Datei: ' . ( is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
				return null;
			}
			if ( ! preg_match( '/^[ \t\/*#@]*Version:\s*([0-9][0-9.]*)/mi', substr( wp_remote_retrieve_body( $res ), 0, 4096 ), $m ) ) {
				$this->protokoll[] = 'Datei: keine Versionsangabe';
				return null;
			}
			$tag   = $this->slug . '-' . $m[1];
			$basis = 'https://github.com/' . self::repo() . '/releases';
			return array(
				array(
					'tag_name'     => $tag,
					'html_url'     => $basis . '/tag/' . rawurlencode( $tag ),
					'published_at' => '',
					'body'         => '',
					'assets'       => array(
						array(
							'name'                 => $this->slug . '.zip',
							'browser_download_url' => $basis . '/download/' . rawurlencode( $tag ) . '/' . $this->slug . '.zip',
						),
					),
				),
			);
		}

		/**
		 * Update-Information in die WordPress-Liste eintragen.
		 *
		 * @param object $transient update_plugins / update_themes.
		 * @return object
		 */
		public function pruefen( $transient ) {
			if ( ! is_object( $transient ) || ( empty( $transient->checked ) && empty( $transient->last_checked ) ) ) {
				return $transient;
			}
			// Installierte Version aus WordPress' eigener Liste (liest die Dateien neu). Die Konstante im
			// geladenen Code kann direkt nach einem Update noch die alte Version enthalten.
			$schluessel  = 'plugin' === $this->typ ? $this->datei : $this->slug;
			$installiert = ! empty( $transient->checked[ $schluessel ] ) ? (string) $transient->checked[ $schluessel ] : $this->version;

			$rel = $this->neuestes();
			$neu = $rel && version_compare( $rel['version'], $installiert, '>' );

			if ( 'plugin' === $this->typ ) {
				$eintrag = (object) array(
					'id'          => $this->datei,
					'slug'        => $this->slug,
					'plugin'      => $this->datei,
					'new_version' => $neu ? $rel['version'] : $installiert,
					'url'         => $rel ? $rel['url'] : '',
					'package'     => $neu ? $rel['package'] : '',
				);
				if ( $neu ) {
					$transient->response[ $this->datei ] = $eintrag;
				} else {
					unset( $transient->response[ $this->datei ] );
					$transient->no_update[ $this->datei ] = $eintrag;
				}
			} else {
				$eintrag = array(
					'theme'       => $this->slug,
					'new_version' => $neu ? $rel['version'] : $installiert,
					'url'         => $rel ? $rel['url'] : '',
					'package'     => $neu ? $rel['package'] : '',
				);
				if ( $neu ) {
					$transient->response[ $this->slug ] = $eintrag;
				} else {
					unset( $transient->response[ $this->slug ] );
					$transient->no_update[ $this->slug ] = $eintrag;
				}
			}
			return $transient;
		}

		/**
		 * „Details ansehen“ beim Plugin: Änderungen aus dem Release.
		 *
		 * @param false|object $res    Ergebnis.
		 * @param string       $action Aktion.
		 * @param object       $args   Argumente.
		 * @return false|object
		 */
		public function info( $res, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
				return $res;
			}
			$rel = $this->neuestes();
			return (object) array(
				'name'          => $this->name,
				'slug'          => $this->slug,
				'version'       => $rel ? $rel['version'] : $this->version,
				'author'        => 'AFK Millstatt – Radenthein',
				'homepage'      => 'https://github.com/' . self::repo(),
				'last_updated'  => $rel ? $rel['datum'] : '',
				'download_link' => $rel ? $rel['package'] : '',
				'sections'      => array(
					'changelog' => $rel && '' !== trim( $rel['notes'] ) ? self::markdown( $rel['notes'] ) : '<p>Keine Angaben.</p>',
				),
			);
		}

		/**
		 * Sehr einfaches Markdown (Überschriften, Listen, fett) für die Änderungsliste.
		 *
		 * @param string $md Text.
		 * @return string HTML.
		 */
		private static function markdown( $md ) {
			$html  = '';
			$liste = false;
			foreach ( preg_split( '/\R/', $md ) as $zeile ) {
				$t = esc_html( trim( $zeile ) );
				$t = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t );
				if ( preg_match( '/^[-*]\s+(.*)$/', $t, $m ) ) {
					$html .= ( $liste ? '' : '<ul>' ) . '<li>' . $m[1] . '</li>';
					$liste = true;
					continue;
				}
				if ( $liste ) {
					$html .= '</ul>';
					$liste = false;
				}
				if ( '' === $t ) {
					continue;
				}
				$html .= preg_match( '/^#+\s*(.*)$/', $t, $m ) ? '<h4>' . $m[1] . '</h4>' : '<p>' . $t . '</p>';
			}
			return $html . ( $liste ? '</ul>' : '' );
		}

		/**
		 * Nach einem Update neu prüfen lassen.
		 */
		public function cache_leeren() {
			delete_site_transient( 'afk_gh_' . $this->slug );
		}
	}
}
