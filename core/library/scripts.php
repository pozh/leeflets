<?php
/**
 * Enqueuing scripts (pulled in from WordPress)
 *
 */

class LF_Scripts extends LF_Assets {
	var $base_url; // Full URL with trailing slash
	var $content_url;
	var $default_version;
	var $in_footer = array();
	var $concat = '';
	var $concat_version = '';
	var $do_concat = false;
	var $print_html = '';
	var $print_code = '';
	var $ext_handles = '';
	var $ext_version = '';
	var $default_dirs;

	private $router;

	function __construct( $base_url, LF_Router $router ) {
		$this->router = $router;
		parent::__construct( $base_url );
		
		$this->add( 'wysihtml5', $router->admin_url( '/core/theme/asset/bootstrap/wysihtml5/js/wysihtml5.js' ), array(), '0.3.0' );
		$this->add( 'jquery', $router->admin_url( '/core/theme/asset/js/jquery.js' ), array(), '1.8.2' );
		$this->add( 'jquery-ui-widget', $router->admin_url( '/core/theme/asset/js/jquery.ui.widget.js' ), array( 'jquery' ), '1.9.1' );
		$this->add( 'jquery-iframe-transport', $router->admin_url( '/core/theme/asset/js/jquery.iframe-transport.js' ), array( 'jquery' ), '1.6.1' );
		$this->add( 'jquery-fileupload', $router->admin_url( '/core/theme/asset/js/jquery.fileupload.js' ), array( 'jquery' ), '5.19.8' );
		$this->add( 'bootstrap', $router->admin_url( '/core/theme/asset/bootstrap/core/js/bootstrap.js' ), array(), '2.2.1' );
		$this->add( 'bootstrap-datepicker', $router->admin_url( '/core/theme/asset/bootstrap/datepicker/js/bootstrap-datepicker.js' ), array( 'bootstrap' ) );
		$this->add( 'bootstrap-wysihtml5', $router->admin_url( '/core/theme/asset/bootstrap/wysihtml5/js/bootstrap-wysihtml5.js' ), array( 'bootstrap' ) );
		$this->add( 'md5', $router->admin_url( '/core/theme/asset/js/md5.js' ), array() );
	}

	/**
	 * Prints scripts
	 *
	 * Prints the scripts passed to it or the print queue. Also prints all necessary dependencies.
	 *
	 * @param mixed   $handles (optional) Scripts to be printed. (void) prints queue, (string) prints that script, (array of strings) prints those scripts.
	 * @param int     $group   (optional) If scripts were queued in groups prints this group number.
	 * @return array Scripts that have been printed
	 */
	function print_scripts( $handles = false, $group = false ) {
		return $this->do_items( $handles, $group );
	}

	// Deprecated since 3.3, see print_extra_script()
	function print_scripts_l10n( $handle, $echo = true ) {
		_deprecated_function( __FUNCTION__, '3.3', 'print_extra_script()' );
		return $this->print_extra_script( $handle, $echo );
	}

	function print_extra_script( $handle, $echo = true ) {
		if ( !$output = $this->get_data( $handle, 'data' ) )
			return;

		if ( !$echo )
			return $output;

		echo "<script type='text/javascript'>\n"; // CDATA and type='text/javascript' is not needed for HTML 5
		echo "/* <![CDATA[ */\n";
		echo "$output\n";
		echo "/* ]]> */\n";
		echo "</script>\n";

		return true;
	}

	function do_item( $handle, $group = false ) {
		if ( !parent::do_item( $handle ) )
			return false;

		if ( 0 === $group && $this->groups[$handle] > 0 ) {
			$this->in_footer[] = $handle;
			return false;
		}

		if ( false === $group && in_array( $handle, $this->in_footer, true ) )
			$this->in_footer = array_diff( $this->in_footer, (array) $handle );

		if ( null === $this->registered[$handle]->ver )
			$ver = '';
		else
			$ver = $this->registered[$handle]->ver ? $this->registered[$handle]->ver : $this->default_version;

		if ( isset( $this->args[$handle] ) )
			$ver = $ver ? $ver . '&amp;' . $this->args[$handle] : $this->args[$handle];

		$src = $this->registered[$handle]->src;

		if ( $this->do_concat ) {
			//$srce = apply_filters( 'script_loader_src', $src, $handle );
			$srce = $src;
			if ( $this->in_default_dir( $srce ) ) {
				$this->print_code .= $this->print_extra_script( $handle, false );
				$this->concat .= "$handle,";
				$this->concat_version .= "$handle$ver";
				return true;
			} else {
				$this->ext_handles .= "$handle,";
				$this->ext_version .= "$handle$ver";
			}
		}

		$this->print_extra_script( $handle );
		if ( !preg_match( '|^(https?:)?//|', $src ) && ! ( $this->content_url && 0 === strpos( $src, $this->content_url ) ) ) {
			$src = $this->base_url . $src;
		}

		if ( !empty( $ver ) )
			$src = LF_String::add_query_arg( 'ver', $ver, $src );

		//$src = esc_url( apply_filters( 'script_loader_src', $src, $handle ) );
		$src = filter_var( $src, FILTER_SANITIZE_URL );

		if ( $this->do_concat )
			$this->print_html .= "<script type=\"text/javascript\" src=\"$src\"></script>\n";
		else
			echo "<script type=\"text/javascript\" src=\"$src\"></script>\n";

		return true;
	}

	/**
	 * Localizes a script
	 *
	 * Localizes only if the script has already been added
	 */
	function localize( $handle, $object_name, $l10n ) {
		if ( is_array( $l10n ) && isset( $l10n['l10n_print_after'] ) ) { // back compat, preserve the code in 'l10n_print_after' if present
			$after = $l10n['l10n_print_after'];
			unset( $l10n['l10n_print_after'] );
		}

		foreach ( (array) $l10n as $key => $value ) {
			if ( !is_scalar( $value ) )
				continue;

			$l10n[$key] = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
		}

		$script = "var $object_name = " . json_encode( $l10n ) . ';';

		if ( !empty( $after ) )
			$script .= "\n$after;";

		$data = $this->get_data( $handle, 'data' );

		if ( !empty( $data ) )
			$script = "$data\n$script";

		return $this->add_data( $handle, 'data', $script );
	}

	function set_group( $handle, $recursion, $group = false ) {

		if ( $this->registered[$handle]->args === 1 )
			$grp = 1;
		else
			$grp = (int) $this->get_data( $handle, 'group' );

		if ( false !== $group && $grp > $group )
			$grp = $group;

		return parent::set_group( $handle, $recursion, $grp );
	}

	function all_deps( $handles, $recursion = false, $group = false ) {
		$r = parent::all_deps( $handles, $recursion );
		//if ( !$recursion )
		//$this->to_do = apply_filters( 'print_scripts_array', $this->to_do );
		return $r;
	}

	function do_head_items() {
		$this->do_items( false, 0 );
		return $this->done;
	}

	function do_footer_items() {
		$this->do_items( false, 1 );
		return $this->done;
	}

	function in_default_dir( $src ) {
		if ( ! $this->default_dirs )
			return true;

		if ( 0 === strpos( $src, '/wp-includes/js/l10n' ) )
			return false;

		foreach ( (array) $this->default_dirs as $test ) {
			if ( 0 === strpos( $src, $test ) )
				return true;
		}
		return false;
	}

	function reset() {
		$this->do_concat = false;
		$this->print_code = '';
		$this->concat = '';
		$this->concat_version = '';
		$this->print_html = '';
		$this->ext_version = '';
		$this->ext_handles = '';
	}
}
