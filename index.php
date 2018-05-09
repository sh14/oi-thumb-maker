<?php
/**
 * Created by PhpStorm.
 * User: Student
 * Date: 29.03.2018
 * Time: 20:59
 */
ini_set( 'memory_limit', '256M' );
error_reporting( E_ALL );
ini_set( "display_error", true );
ini_set( "error_reporting", E_ALL );
if ( file_exists( 'tree.php' ) ) {

	include 'tree.php';
}

function pr( $data ) {
	echo '<pre>';
	print_r( $data );
	echo '</pre>';
}

function get_site_path() {
	return __DIR__;
}

function get_site_url() {

	// если переменная HTTPS установлена и не равна off(на серверах IIS если запрос идет не через https)
	$protocol = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http';

	return $protocol
	       . '://'
	       . $_SERVER['HTTP_HOST']
	       . dirname( $_SERVER['PHP_SELF'] )
	       . '/';
}

// for jpg
function resize_image( $file, $new_path, $width, $height, $crop = false ) {
	//$info = pathinfo( basename( $file ) );
	$extension = mime_content_type( $file );

	if ( strpos( $extension, 'image' ) === 0 ) {
		$extension = str_replace( 'image/', '', $extension );
		$extension = strtolower( $extension );

		if ( ! empty( $extension ) ) {

			$type = $extension;
			if ( $type == 'jpeg' ) {
				$type = 'jpg';
			}

			list( $width_orig, $height_orig ) = getimagesize( $file );

			switch ( $type ) {
				case 'jpg':

					$src = imagecreatefromjpeg( $file );
					break;
				case 'png':
					$src = imagecreatefrompng( $file );
					break;
				case 'gif':
					$src = imagecreatefromgif( $file );
					break;
				case 'bmp':
					$src = imagecreatefromwbmp( $file );
					break;
				default:
					return '';
			}
			$x = 0;
			$y = 0;

			if ( $width_orig > $height_orig ) {
				$new_height = $height;
				$new_width  = floor( $width_orig * ( $new_height / $height_orig ) );
				$crop_x     = ceil( ( $width_orig - $height_orig ) / 2 );
				$crop_y     = 0;
			} else {
				$new_width  = $width;
				$new_height = floor( $height_orig * ( $new_width / $width_orig ) );
				$crop_x     = 0;
				$crop_y     = ceil( ( $height_orig - $width_orig ) / 2 );
			}

			$dst = imagecreatetruecolor( $width, $height );

			// preserve transparency
			if ( $type == 'gif' || $type == 'png' ) {
				imagecolortransparent( $dst, imagecolorallocatealpha( $dst, 0, 0, 0, 127 ) );
				imagealphablending( $dst, false );
				imagesavealpha( $dst, true );
			}

			imagecopyresampled( $dst, $src, 0, 0, $crop_x, $crop_y, $new_width, $new_height, $width_orig, $height_orig );

			switch ( $type ) {
				case 'bmp':
					imagewbmp( $dst, $new_path );
					break;
				case 'gif':
					imagegif( $dst, $new_path );
					break;
				case 'jpg':
					imagejpeg( $dst, $new_path );
					break;
				case 'png':
					imagepng( $dst, $new_path );
					break;
			}
		}
	}
}

/**
 * Scan directories and make tree array of files
 *
 * @param $atts
 *
 * @return array
 */
function scan( $atts, $source = '' ) {
	$atts = parse_args( $atts, array(
		// directory name that should be scanned
		'source'     => '',
		'dir_prefix' => 'dir:',
		// file extensions that should be added in an array
		'extensions' => array(),
		'exclude'    => array('.', '..'),
	) );

	$dir_name = basename( $source );
	$array    = scandir( $source ); // get all items in dir
	$tree     = array();

	// loop dir items
	foreach ( $array as $item ) {
		// exclude .. and .
		if ( ! in_array( $item, $atts['exclude'] ) ) {

			//pr($source);
			$path = $source . '/' . $item;
			// if item is a dir
			if ( is_dir( $path ) ) {

				// run scan for dir
				$tree[ $atts['dir_prefix'] . $dir_name ][ $atts['dir_prefix'] . $item ] = array_up_to_level( scan( $atts, $path ) );

			} else {
				// get path info
				$pathinfo = pathinfo( $path );

				// if $atts['extensions'] is empty or not empty and file extension in array
				if ( empty( $atts['extensions'] ) || ( ! empty( $atts['extensions'] ) && in_array( $pathinfo['extension'], $atts['extensions'] ) ) ) {

					// add file data to an array with unique index for data protecting
					$tree[ $atts['dir_prefix'] . $dir_name ][ md5( $item ) ] = $item;
				}
			}
		}
	}

	return $tree;
}

/**
 * Функция парсинга массива, со вставкой дефолтных значений в пустые элементы
 *
 * @param $atts
 * @param $defaults
 */
function parse_args( $atts, $defaults ) {
	foreach ( $defaults as $key => $value ) {
		if ( empty( $atts[ $key ] ) ) {
			$atts[ $key ] = $defaults[ $key ];
		}
	}

	return $atts;
}

/**
 * Create thumbs in dir
 *
 * @param       $tree
 * @param array $args
 */
function create_thumbs( $tree, $args = array(), $path = '' ) {
	$args = parse_args( $args, array(
		// path with original files
		'source'      => '',
		'destination' => '',
		'path'        => 'images/thumb',
		'dir_prefix'  => 'dir:',
		'width'       => 150,
		'height'      => 150,
		'crop'        => true,
		'regenerate'  => false,
		'iteration'   => 0,
	) );

	if ( ! empty( $tree ) ) {

		if ( $args['iteration'] == 0 ) {
			$path = $args['path'] . '/' ;
			$args['iteration'] ++;
		}

		if ( ! file_exists( $path ) ) {
			mkdir( $path );
		}



		// loop an array
		foreach ( $tree as $key => $value ) {

			// if current item is array(dir)
			if ( is_array( $value ) ) {
				$path .= str_replace( $args['dir_prefix'], '', $key ) . '/';
				//
				// create dir if not exists
				create_thumbs( $value, $args, $path );
			} else {
				//pr( $path );
				$dir       = explode( '/', $args['source'] );
				$dir       = array_slice( $dir, 0, sizeof( $dir ) - 1 );
				$dir       = implode( '/', $dir );
				$file_path = $dir . '/' . $path . $value;
				$new_path =  $path . $value;

				$original       = explode( '/', $args['source'] );
				$original = array_slice( $original, 0, sizeof( $original) - 1 );
				$original       = implode( '/', $original );
				// if file not exists or need to be regenerated
				if ( ! file_exists( $new_path ) || $args['regenerate'] == true ) {


					pr( $args['iteration'].': '.$original.' => '.$new_path );
					//      pr( $args['path'] . '/' . $file_path );
					// create thumb
					resize_image( $file_path, $new_path, $args['width'], $args['height'], $args['crop'] );
					//ob_flush();
				}
			}
		}
	}
}

/**
 * Get data of subarray by given rout
 *
 * @param $rout
 *
 * @return array
 */
function get_rout_data( $rout ) {

	$data = array();
	if ( function_exists( 'get_files_data' ) ) {

		if ( ! empty( $rout ) ) {
			$data = get_files_data();
			$rout = explode( '/', $rout );

			foreach ( $rout as $key ) {

				if ( ! empty( $data[ $key ] ) ) {
					$data = $data[ $key ];
				} else {
					return array();
				}
			}
		}
	}

	return $data;
}

/**
 * Updating of an array with new files
 *
 * @param       $array
 * @param array $args
 *
 * @return array
 */
function update_data( $array, $args = array() ) {
	$args = parse_args( $args, array(
		// path with original files
		'source'     => '',
		'path'       => 'thumb',
		'path_dir'   => '',
		'dir_prefix' => 'dir:',
		'rout'       => array(),
	) );

	// prepare substing for replacing
	if ( ! empty( $args['source'] ) ) {
		$args['source'] .= '/';
	}

	$path_dir = trim( str_replace( $args['path'] . '/' . $args['source'], '', $args['path_dir'] ), '/' );

	$new_array = array();
	foreach ( $array as $index => $value ) {

		if ( is_array( $value ) ) {

			// prepare dir name
			$index               = str_replace( $args['dir_prefix'], '', $index );
			$args['path_dir']    = $path_dir . '/' . $index;
			$args['rout'][]      = $index;
			$new_array[ $index ] = update_data( $value, $args );
		} else {
			$default = array(
				'file'        => '',
				'title'       => '',
				'description' => '',
			);

			// get old data for given file
			$old_data = get_rout_data( $path_dir . '/' . $index );

			// get old data about current file
			$array_data = parse_args( $old_data, $default );

			// add new exists file path
			$array_data['file'] = $path_dir . '/' . $value;

			// add data to an array
			$new_array[ $index ] = $array_data;
		}
	}

	return $new_array;
}


function array_up_to_level( $arr ) {
	$new = array();
	foreach ( $arr as $i => $value ) {
		foreach ( $value as $val ) {
			if ( is_array( $val ) ) {
				$new += $value;
			} else {
				if ( empty( $new[ $i ] ) ) {

					$new = $value;
				}
			}
		}
	}

	return $new;
}

function array_add( $old, $new ) {
	$array = array();

}


function show_images( $data, $atts = array() ) {
	$atts = parse_args( $atts, array(
		'site_url' => '',
		'site_dir' => '',
		'path'     => 'thumb',
	) );

	$gallery = array();
	foreach ( $data as $image ) {
		if ( ! empty( $image['file'] ) ) {
			$gallery[] = '<a href="' . trim( $atts['site_url'], '/' ) . '/' . $image['file'] . '">'
			             . '<img src="' . trim( $atts['site_url'], '/' ) . '/' . $atts['path'] . '/' . $image['file'] . '" alt="' . $image['title'] . '">'
			             . $image['description']
			             . '</a>';
		}
	}
	$gallery = implode( "\n", $gallery );
	echo $gallery;

}

function run_thumb_maker( $atts ) {
	$atts = parse_args( $atts, array(
		'extensions' => array('jpg', 'jpeg', 'gif', 'png',),
		'regenerate' => false,
		'dir_prefix' => 'dir:',
		'exclude'    => array('.', '..'),
		'source'     => '',
		'path'       => 'thumb',
		'width'      => 150,
		'height'     => 150,
		'crop'       => true,
	) );


	// scan directory
	$tree = scan( $atts, $atts['source'] );
	pr( $tree );

	ob_flush();
	// create thumbs for images
	create_thumbs( $tree, $atts, $atts['path'] );

	// update an array with description
	$tree = update_data( $tree );

//

	if ( file_exists( 'tree.php' ) ) {


		$content = '<?php' . "\n"
		           . 'function get_files_data(){'
		           . 'return '
		           . var_export( $tree, true )
		           . ';'
		           . '}';
		file_put_contents( 'tree.php', $content );

	}
}

run_thumb_maker( array(
	'source' => 'images/uploads',
	'path'   => 'images/thumb',
	'regenerate' => true,
) );

if ( function_exists( 'get_files_data' ) ) {
	$data = get_files_data();
	if ( ! empty( $data['uploads']['Nolte_files'] ) ) {
		show_images( $data['uploads']['Nolte_files'], array(
			'site_url' => get_site_url(),
			'site_dir' => get_site_path(),
		) );
	}
}

//pr($tree);


//  София \d{2}:\d{2}([\s\S]*?)Алексей
