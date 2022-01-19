<?php

namespace WP_CLI\StackPlugin;

use WP_CLI;

if ( ! class_exists( '\WP_CLI' ) ) {
	return;
}

$wpcli_hello_world_autoloader = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $wpcli_hello_world_autoloader ) ) {
	require_once $wpcli_hello_world_autoloader;
}

WP_CLI::add_command( 'scaffold stackplugin', StackPluginCommand::class );
