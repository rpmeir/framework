<?php

use Adianti\Core\AdiantiApplicationConfig;
use Adianti\Core\AdiantiCoreApplication;

/**
 * Route handler
 *
 * @version    5.0
 * @package    Web
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class Route
{
    public static $routes;
    public static $exception;
    
    /**
     * Register an endpoint
     * 
     * @param $endpoint entry point
     * @param $callback response callback
     */
    public static function on($method, $endpoint, $callback)
    {
        // testar para não aceitar duplicado
        // criar método exists
        self::$routes[$method.'/'.$endpoint] = $callback;
    }

    public static function head($endpoint, $callback)
    {
        self::on('HEAD', $endpoint, $callback);
    }

    public static function get($endpoint, $callback)
    {
        self::on('GET', $endpoint, $callback);
    }

    public static function post($endpoint, $callback)
    {
        self::on('POST', $endpoint, $callback);
    }

    public static function put($endpoint, $callback)
    {
        self::on('PUT', $endpoint, $callback);
    }

    public static function patch($endpoint, $callback)
    {
        self::on('PATCH', $endpoint, $callback);
    }

    public static function delete($endpoint, $callback)
    {
        self::on('DELETE', $endpoint, $callback);
    }

    public static function options($endpoint, $callback)
    {
        self::on('OPTIONS', $endpoint, $callback);
    }
    
    /**
     * Execute an endpoint
     * 
     * @param $endpoint entry point
     * @param $args arguments
     */
    public static function exec($method, $endpoint, $args)
    {
        call_user_func(self::$routes[$method.'/'.$endpoint], $args);
    }
    
    /**
     * Register an exception
     * 
     * @param $callback response callback
     */
    public static function exception($callback)
    {
        self::$exception = $callback;
    }
    
    /**
     * Execute the current URL action
     */
    public static function run()
    {

        $ini      = AdiantiApplicationConfig::get();
        $input    = json_decode(file_get_contents("php://input"), true);
        $request  = array_merge($_REQUEST, (array) $input);

        $class    = isset($request['class']) ? $request['class']   : '';
        $method   = isset($request['method']) ? $request['method'] : '';
        $headers  = AdiantiCoreApplication::getHeaders();
        $response = NULL;
        
        $headers['Authorization'] = $headers['Authorization'] ?? ($headers['authorization'] ?? null); // for clientes that send in lowercase (Ex. futter)
        
        try
        {
            if (empty($headers['Authorization']))
            {
                throw new Exception( _t('Authorization error') );
            }
            else
            {
                if (substr($headers['Authorization'], 0, 5) == 'Basic')
                {
                    if (empty($ini['general']['rest_key']))
                    {
                        throw new Exception( _t('REST key not defined') );
                    }
					
                    if ($ini['general']['rest_key'] !== substr($headers['Authorization'], 6))
                    {
                        http_response_code(401);
                        return json_encode( array('status' => 'error', 'data' => _t('Authorization error')));
                    }
                }
                else if (substr($headers['Authorization'], 0, 6) == 'Bearer')
                {
                    ApplicationAuthenticationService::fromToken( substr($headers['Authorization'], 7) );
                }
                else
                {
                    http_response_code(403);
                    throw new Exception( _t('Authorization error') );
                }
            }
            
            $path_info = (isset($_SERVER['PATH_INFO'])) ? str_replace("api.php/", "", trim($_SERVER['PATH_INFO'], '/')) : '';
            $verb = $_SERVER['REQUEST_METHOD'];

            if (!isset($path_info))
            {
                $callback = self::$routes[''];
            }
            else
            {
                // TODO
                // utilizar o método exists com preg_grep para localizar o endpoint
                
                if(isset(self::$routes[$verb.'/'.$path_info]))
                {
                    $callback = self::$routes[$verb.'/'.$path_info];
                }
                else
                {
                    $callback = null;
                    http_response_code(403);
                    return json_encode([
                        "erro" => "Endpoint inexistente", 
                        "msg" => "Endpoint não localizado neste método"]
                    );
                }
            }
            
            if (is_callable($callback))
            {
                try
                {
                    call_user_func($callback);
                }
                catch (Exception $e)
                {
                    if (is_callable(self::$exception))
                    {
                        call_user_func(self::$exception, $e);
                    }
                }
            }

            $response = AdiantiCoreApplication::execute($class, $method, $request, 'rest');
            
            if (is_array($response))
            {
                array_walk_recursive($response, ['AdiantiStringConversion', 'assureUnicode']);
            }
            return json_encode( array('status' => 'success', 'data' => $response));
        }
        catch (Exception $e)
        {
            if(200 === http_response_code())
            {
                http_response_code(500);
            }

            return json_encode( array('status' => 'error', 'data' => $e->getMessage()));
        }
        catch (Error $e)
        {
            if(200 === http_response_code())
            {
                http_response_code(500);
            }

            return json_encode( array('status' => 'error', 'data' => $e->getMessage()));
        }
    }
}
