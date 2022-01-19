<?php

namespace WP_CLI\StackPlugin;

use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils;
use WP_CLI\Inflector;

class StackPluginCommand extends WP_CLI_Command {

	/**
	 * Generates starter code for a plugin.
	 *
	 * The following files are always generated:
	 *
	 * * `plugin-slug.php` is the main PHP plugin file.
	 * * `readme.txt` is the readme file for the plugin.
	 * * `package.json` needed by NPM holds various metadata relevant to the project. Packages: `grunt`, `grunt-wp-i18n` and `grunt-wp-readme-to-markdown`. Scripts: `start`, `readme`, `i18n`.
	 * * `Gruntfile.js` is the JS file containing Grunt tasks. Tasks: `i18n` containing `addtextdomain` and `makepot`, `readme` containing `wp_readme_to_markdown`.
	 * * `.editorconfig` is the configuration file for Editor.
	 * * `.gitignore` tells which files (or patterns) git should ignore.
	 * * `.distignore` tells which files and folders should be ignored in distribution.
	 *
	 * The following files are also included unless the `--skip-tests` is used:
	 *
	 * * `phpunit.xml.dist` is the configuration file for PHPUnit.
	 * * `.travis.yml` is the configuration file for Travis CI. Use `--ci=<provider>` to select a different service.
	 * * `bin/install-wp-tests.sh` configures the WordPress test suite and a test database.
	 * * `tests/bootstrap.php` is the file that makes the current plugin active when running the test suite.
	 * * `tests/test-sample.php` is a sample file containing test cases.
	 * * `.phpcs.xml.dist` is a collection of PHP_CodeSniffer rules.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The internal name of the plugin.
	 * 
	 * <namespace>
	 * : The internal name of the plugin.
	 *
	 * [--dir=<dirname>]
	 * : Put the new plugin in some arbitrary directory path. Plugin directory will be path plus supplied slug.
	 *
	 * [--plugin_name=<title>]
	 * : What to put in the 'Plugin Name:' header.
	 *
	 * [--plugin_description=<description>]
	 * : What to put in the 'Description:' header.
	 *
	 * [--plugin_author=<author>]
	 * : What to put in the 'Author:' header.
	 *
	 * [--plugin_author_uri=<url>]
	 * : What to put in the 'Author URI:' header.
	 *
	 * [--plugin_uri=<url>]
	 * : What to put in the 'Plugin URI:' header.
	 *
	 * [--skip-tests]
	 * : Don't generate files for unit testing.
	 *
	 * [--ci=<provider>]
	 * : Choose a configuration file for a continuous integration provider.
	 * ---
	 * default: travis
	 * options:
	 *   - travis
	 *   - circle
	 *   - gitlab
	 * ---
	 *
	 * [--activate]
	 * : Activate the newly generated plugin.
	 *
	 * [--activate-network]
	 * : Network activate the newly generated plugin.
	 *
	 * [--force]
	 * : Overwrite files that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp scaffold stackplugin sample-plugin
	 *     Success: Created plugin files.
	 *     Success: Created test files.
	 */
	public function __invoke( $args, $assoc_args ) {
		$plugin_slug    = $args[0];
		$plugin_namespace = $args[1];
		$plugin_name    = ucwords( str_replace( '-', ' ', $plugin_slug ) );
		$plugin_package = str_replace( ' ', '_', $plugin_name );
		$slug_arr = explode('-',$plugin_slug);
		$plugin_class = $slug_arr[count($slug_arr)-1];

		if ( in_array( $plugin_slug, [ '.', '..' ], true ) ) {
			WP_CLI::error( "Invalid plugin slug specified. The slug cannot be '.' or '..'." );
		}

		$defaults = [
			'plugin_slug'         => $plugin_slug,
			'plugin_name'         => $plugin_name,
			'plugin_namespace'    => $plugin_namespace,
			'plugin_namespace_lower'    => strtolower($plugin_namespace,
			'plugin_class'    	  => $plugin_class,
			'plugin_class_lower'  => strtolower($plugin_class),
			'plugin_package'      => $plugin_package,
			'plugin_description'  => 'PLUGIN DESCRIPTION HERE',
			'plugin_author'       => 'YOUR NAME HERE',
			'plugin_author_uri'   => 'YOUR SITE HERE',
			'plugin_uri'          => 'PLUGIN SITE HERE',
			'plugin_tested_up_to' => get_bloginfo( 'version' ),
		];
		$data     = wp_parse_args( $assoc_args, $defaults );

		$data['textdomain'] = $plugin_slug;

		if ( ! empty( $assoc_args['dir'] ) ) {
			if ( ! is_dir( $assoc_args['dir'] ) ) {
				WP_CLI::error( "Cannot create plugin in directory that doesn't exist." );
			}
			$plugin_dir = "{$assoc_args['dir']}/{$plugin_slug}";
		} else {
			$plugin_dir = WP_PLUGIN_DIR . "/{$plugin_slug}";
			$this->maybe_create_plugins_dir();

			$error_msg = $this->check_target_directory( 'plugin', $plugin_dir );
			if ( ! empty( $error_msg ) ) {
				WP_CLI::error( "Invalid plugin slug specified. {$error_msg}" );
			}
		}

		$plugin_path        = "{$plugin_dir}/{$plugin_slug}.php";
		$plugin_readme_path = "{$plugin_dir}/readme.txt";

		$files_to_create = [
			$plugin_path                  => self::mustache_render( 'plugin.mustache', $data ),
			$plugin_readme_path           => self::mustache_render( 'plugin-readme.mustache', $data ),
			"{$plugin_dir}/composer.json"  => self::mustache_render( 'plugin-composer.mustache', $data ),
			"{$plugin_dir}/package.json"  => self::mustache_render( 'plugin-packages.mustache', $data ),
			"{$plugin_dir}/Gruntfile.js"  => self::mustache_render( 'plugin-gruntfile.mustache', $data ),
			"{$plugin_dir}/.gitignore"    => self::mustache_render( 'plugin-gitignore.mustache', $data ),
			"{$plugin_dir}/.distignore"   => self::mustache_render( 'plugin-distignore.mustache', $data ),
			"{$plugin_dir}/.editorconfig" => file_get_contents( self::get_template_path( '.editorconfig' ) ),
			"{$plugin_dir}/admin/class-".$data['plugin_class_lower']."-admin.php"   => self::mustache_render( 'plugin-admin.mustache', $data ),
			"{$plugin_dir}/admin/class-".$data['plugin_class_lower']."-rest.php"   => self::mustache_render( 'plugin-admin-rest.mustache', $data ),
			"{$plugin_dir}/admin/class-".$data['plugin_class_lower']."-ajax.php"   => self::mustache_render( 'plugin-admin-ajax.mustache', $data ),
			"{$plugin_dir}/admin/class-".$data['plugin_class_lower']."-settings.php"   => self::mustache_render( 'plugin-admin-settings.mustache', $data ),
			"{$plugin_dir}/includes/class-".$data['plugin_class_lower'].".php"   => self::mustache_render( 'plugin-includes.mustache', $data ),
			"{$plugin_dir}/includes/class-".$data['plugin_class_lower']."-loader.php"   => self::mustache_render( 'plugin-includes-loader.mustache', $data ),
			"{$plugin_dir}/includes/class-".$data['plugin_class_lower']."-i18n.php"   => self::mustache_render( 'plugin-includes-i18n.mustache', $data ),
			"{$plugin_dir}/includes/class-".$data['plugin_class_lower']."-deactivator.php"   => self::mustache_render( 'plugin-includes-deactivator.mustache', $data ),
			"{$plugin_dir}/includes/class-".$data['plugin_class_lower']."-cli.php"   => self::mustache_render( 'plugin-includes-cli.mustache', $data ),
			"{$plugin_dir}/includes/cli/class-".$data['plugin_class_lower']."-command.php"   => self::mustache_render( 'plugin-includes-cli-command.mustache', $data ),
			"{$plugin_dir}/includes/cli/class-".$data['plugin_class_lower']."-subcomannd.php"   => self::mustache_render( 'plugin-includes-cli-subcommand.mustache', $data ),
			"{$plugin_dir}/includes/class-".$data['plugin_class_lower']."-activator.php"   => self::mustache_render( 'plugin-includes-activator.mustache', $data ),
			"{$plugin_dir}/frontend/class-".$data['plugin_class_lower']."-frontend.php"   => self::mustache_render( 'plugin-frontend.mustache', $data ),

		];
		$force           = Utils\get_flag_value( $assoc_args, 'force' );
		$files_written   = $this->create_files( $files_to_create, $force );

		$skip_message    = 'All plugin files were skipped.';
		$success_message = 'Created plugin files.';
		$this->log_whether_files_written( $files_written, $skip_message, $success_message );

		if ( ! Utils\get_flag_value( $assoc_args, 'skip-tests' ) ) {
			$command_args = [
				'dir'   => $plugin_dir,
				'ci'    => empty( $assoc_args['ci'] ) ? '' : $assoc_args['ci'],
				'force' => $force,
			];
			WP_CLI::run_command( [ 'scaffold', 'plugin-tests', $plugin_slug ], $command_args );
		}

		if ( Utils\get_flag_value( $assoc_args, 'activate' ) ) {
			WP_CLI::run_command( [ 'plugin', 'activate', $plugin_slug ] );
		} elseif ( Utils\get_flag_value( $assoc_args, 'activate-network' ) ) {
			WP_CLI::run_command( [ 'plugin', 'activate', $plugin_slug ], [ 'network' => true ] );
		}
	}
		/**
	 * Checks that the `$target_dir` is a child directory of the WP themes or plugins directory, depending on `$type`.
	 *
	 * @param string $type       "theme" or "plugin"
	 * @param string $target_dir The theme/plugin directory to check.
	 *
	 * @return null|string Returns null on success, error message on error.
	 */
	private function check_target_directory( $type, $target_dir ) {
		$parent_dir = dirname( self::canonicalize_path( str_replace( '\\', '/', $target_dir ) ) );

		if ( 'theme' === $type && str_replace( '\\', '/', WP_CONTENT_DIR . '/themes' ) !== $parent_dir ) {
			return sprintf( 'The target directory \'%1$s\' is not in \'%2$s\'.', $target_dir, WP_CONTENT_DIR . '/themes' );
		}

		if ( 'plugin' === $type && str_replace( '\\', '/', WP_PLUGIN_DIR ) !== $parent_dir ) {
			return sprintf( 'The target directory \'%1$s\' is not in \'%2$s\'.', $target_dir, WP_PLUGIN_DIR );
		}

		// Success.
		return null;
	}

	protected function create_files( $files_and_contents, $force ) {
		$wp_filesystem = $this->init_wp_filesystem();
		$wrote_files   = [];

		foreach ( $files_and_contents as $filename => $contents ) {
			$should_write_file = $this->prompt_if_files_will_be_overwritten( $filename, $force );
			if ( ! $should_write_file ) {
				continue;
			}

			$wp_filesystem->mkdir( dirname( $filename ) );

			if ( ! $wp_filesystem->put_contents( $filename, $contents ) ) {
				WP_CLI::error( "Error creating file: {$filename}" );
			} elseif ( $should_write_file ) {
				$wrote_files[] = $filename;
			}
		}
		return $wrote_files;
	}

	protected function prompt_if_files_will_be_overwritten( $filename, $force ) {
		$should_write_file = true;
		if ( ! file_exists( $filename ) ) {
			return true;
		}

		WP_CLI::warning( 'File already exists.' );
		WP_CLI::log( $filename );
		if ( ! $force ) {
			do {
				$answer      = cli\prompt(
					'Skip this file, or replace it with scaffolding?',
					$default = false,
					$marker  = '[s/r]: '
				);
			} while ( ! in_array( $answer, [ 's', 'r' ], true ) );
			$should_write_file = 'r' === $answer;
		}

		$outcome = $should_write_file ? 'Replacing' : 'Skipping';
		WP_CLI::log( $outcome . PHP_EOL );

		return $should_write_file;
	}

	protected function log_whether_files_written( $files_written, $skip_message, $success_message ) {
		if ( empty( $files_written ) ) {
			WP_CLI::log( $skip_message );
		} else {
			WP_CLI::success( $success_message );
		}
	}

	protected function extract_args( $assoc_args, $defaults ) {
		$out = [];

		foreach ( $defaults as $key => $value ) {
			$out[ $key ] = Utils\get_flag_value( $assoc_args, $key, $value );
		}

		return $out;
	}

	protected function quote_comma_list_elements( $comma_list ) {
		return "'" . implode( "', '", explode( ',', $comma_list ) ) . "'";
	}

	/**
	 * Creates the plugins directory if it doesn't already exist.
	 */
	protected function maybe_create_plugins_dir() {

		if ( ! is_dir( WP_PLUGIN_DIR ) ) {
			wp_mkdir_p( WP_PLUGIN_DIR );
		}

	}

	/**
	 * Initializes WP_Filesystem.
	 */
	protected function init_wp_filesystem() {
		global $wp_filesystem;
		WP_Filesystem();

		return $wp_filesystem;
	}

	/**
	 * Localizes the template path.
	 */
	private static function mustache_render( $template, $data = [] ) {
		return Utils\mustache_render( dirname( dirname( __FILE__ ) ) . "/templates/{$template}", $data );
	}

	/**
	 * Gets the template path based on installation type.
	 */
	private static function get_template_path( $template ) {
		$command_root  = Utils\phar_safe_path( dirname( __DIR__ ) );
		$template_path = "{$command_root}/templates/{$template}";

		if ( ! file_exists( $template_path ) ) {
			WP_CLI::error( "Couldn't find {$template}" );
		}

		return $template_path;
	}

	/*
	 * Returns the canonicalized path, with dot and double dot segments resolved.
	 *
	 * Copied from Symfony\Component\DomCrawler\AbstractUriElement::canonicalizePath().
	 * Implements RFC 3986, section 5.2.4.
	 *
	 * @param string $path The path to make canonical.
	 *
	 * @return string The canonicalized path.
	 */
	private static function canonicalize_path( $path ) {
		if ( '' === $path || '/' === $path ) {
			return $path;
		}

		if ( '.' === substr( $path, -1 ) ) {
			$path .= '/';
		}

		$output = [];

		foreach ( explode( '/', $path ) as $segment ) {
			if ( '..' === $segment ) {
				array_pop( $output );
			} elseif ( '.' !== $segment ) {
				$output[] = $segment;
			}
		}

		return implode( '/', $output );
	}
}
