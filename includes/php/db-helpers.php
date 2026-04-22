<?php
namespace SIM;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

if(!isset($_SESSION)){
	session_start();
}

/**
 * Search every table and column in the db
 *
 * @param	string	$search				the searchstring
 * @param	array	$excludedTables		the tables to exclude from the search
 * @param	array	$excludedColumns	the columns to exclude from the search
 *
 * @return	array						An array of results
 */
function searchAllDB($search, $excludedTables=[], $excludedColumns=[]){
    global $wpdb;

    $out 	= [];

    $tables	= $wpdb->get_results("show tables", ARRAY_N);
    if(!empty($tables)){
        foreach($tables as $table){
			if(in_array($table[0], $excludedTables)){
				continue;
			}

            $sqlSearchFields 	= [];
            
            $columns 			= $wpdb->get_results(
				$wpdb->prepare("SHOW COLUMNS FROM %i", $table[0])
			);
            if(!empty($columns)){
                foreach($columns as $column){
					if(in_array($column->Field, $excludedColumns)){
						continue;
					}

                    $sqlSearchFields[] = "`".$column->Field."` like('%".$wpdb->_real_escape($search)."%')";
                }
            }
            $results		= $wpdb->get_results(
				$wpdb->prepare("select * from %i where %s", $table[0], implode(" OR ", $sqlSearchFields))
			);
			if(!empty($results)){
				foreach($results as $result){
					foreach($result as $column=>$value){
						if(in_array($column, $excludedColumns)){
							continue;
						}
						if(str_contains($value, $search)){
							$out[] 	= [
								'table'		=> $table[0],
								'column'	=> $column,
								'value'		=> $value,
							];
						}
					}
				}
			}
        }
    }

	foreach($out as $index=>&$result){
		$match	= false;
		$value	= maybe_unserialize($result['value']);
		if(is_array($value)){
			$found	= arraySearchRecursive($search, $result);
			if(!empty($found)){
				$match	= true;
				$result	= $found;
			}
		}elseif($value == $search){
			$match	= true;
		}

		if(!$match){
			unset($out[$index]);
		}
	}

    return array_values($out);
}

/**
 * Temporary store a value
 *
 * @param   string  $key        The identifier
 * @param   string|int|array|object     $value  The value
 */
function storeInTransient($key, $value){
    $_SESSION[$key] = $value;
}

function recursiveSanitizeMixedValue( $value ) {
    if ( is_array( $value ) ) {
        // Recursively sanitize each element in the array
        foreach ( $value as $key => &$child_value ) {
            $child_value = recursiveSanitizeMixedValue( $child_value );
        }
        return $value;
    } else {
        // Sanitize string/int values
        return sanitize_text_field( $value );
    }
}

/**
 * Retrieves a temporary stored value
 *
 * @param   string  $key    The key the values was stored with
 *
 * @return  mixed			The value or false if no value
 */
function getFromTransient($key){
	if(!isset($_SESSION[$key])){
		return false;
	}

    $value  = $_SESSION[$key]; 

	$value  = recursiveSanitizeMixedValue($_SESSION[$key]); 

    return $value;
}

/**
 * Deletes a temporary stored value
 *
 * @param   string  $key    The key the values was stored with
 *
 * @return  string|int|array|object             The value
 */
function deleteFromTransient($key){
    if(!isset($_SESSION)){
        session_start();
    }
    unset( $_SESSION[$key]);

    session_write_close();
}

/**
 	* Get a value from the db, or cache
	* @param string      $cacheKey  The key to identify the cache value
 	* @param string      $query   	Query statement with `sprintf()`-like placeholders.
	* @param mixed       ...$args 	Variables to substitute into the query's placeholders if being called with individual arguments.
	*/
function getFromDb($cacheKey, $query, ...$args ){
	global $wpdb;

	$function = 'get_results';
	if(
		str_contains($query, 'select count(') ||
		str_contains($query, 'select sum(') ||
		str_contains($query, 'select avg(') ||
		str_contains($query, 'select max(') ||
		str_contains($query, 'select min(') ||
		str_ends_with($query, 'LIMIT 1')
	){
		$function = 'get_var';
	}else if(!str_contains($query, 'select * from')){
		$function = 'get_col';
	}

	$value = wp_cache_get( $cacheKey, 'tsjippy-shared-functionality', false, $found  );

	if ( !$found) {
		$value = $wpdb->$function(
			$wpdb->prepare($query, ...$args)
		);

		if($wpdb->last_error !== ''){
            return new \WP_Error('db', $wpdb->last_error);
        }

		wp_cache_set( $cacheKey, $value, 'tsjippy-shared-functionality' );
	}

	return $value;
}