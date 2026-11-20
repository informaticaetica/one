<?php
/**
 * Disinstallazione: rimuove tabelle e opzioni del plugin.
 * Le schede (contenuti) restano nel database, come tutti i contenuti di WordPress:
 * si cancellano a mano dalla bacheca se davvero non servono più.
 *
 * @package StatisticheTop
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}st_voti" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}st_segnalazioni" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( array( 'st_impostazioni', 'st_versione_db', 'st_media_generale', 'st_ultimo_calcolo' ) as $opzione ) {
	delete_option( $opzione );
}
delete_metadata( 'user', 0, 'st_giorni_accesso', '', true );
delete_metadata( 'user', 0, 'st_conflitti', '', true );
foreach ( array( 'administrator', 'editor' ) as $nome ) {
	$ruolo = get_role( $nome );
	if ( $ruolo ) {
		$ruolo->remove_cap( 'st_modera' );
	}
}
wp_clear_scheduled_hook( 'st_calcolo_notturno' );
