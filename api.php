<?php

use Adianti\Core\AdiantiApplicationConfig;
use Adianti\Core\AdiantiCoreApplication;

header('Content-Type: application/json; charset=utf-8');

// initialization script
require_once 'init.php';

class AdiantiRestServer
{
    public static function run($request)
    {
        $ini      = AdiantiApplicationConfig::get();
        $input    = json_decode(file_get_contents("php://input"), true);
        $request  = array_merge($request, (array) $input);

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

            $service = isset($ini['general']['request_log_service']) ? $ini['general']['request_log_service'] : '\SystemRequestLogService';
            $endpoint = 'rest';
            
            if (!empty($ini['general']['request_log']) && $ini['general']['request_log'] == '1')
            {
                if (empty($endpoint) || empty($ini['general']['request_log_types']) || strpos($ini['general']['request_log_types'], $endpoint) !== false)
                {
                    $service::register( $endpoint );
                }
            }

            $response = self::dispatch();

            if (is_array($response))
            {
                array_walk_recursive($response, ['AdiantiStringConversion', 'assureUnicode']);
            }

            return json_encode( $response );
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

    public static function dispatch ()
    {
        
        // Fetch method and URI from somewhere
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];

        // Strip query string (?foo=bar) and decode URI
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }

        // When the API endpoint is not the main file
        $endpoint = pathinfo(__FILE__, PATHINFO_BASENAME) . '/';

        $uri = rawurldecode($uri);
        $uri = str_replace($endpoint, '', $uri);

        $dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
            $r->addGroup('/v1', function() use ($r) {
                require __DIR__ . '/app/api/v1/routes.php';
            });
        });

        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case FastRoute\Dispatcher::NOT_FOUND:
                http_response_code(404);
                return ['msg' => 'Desculpe, rota nao encontrada'];
                break;
            case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                http_response_code(405);
                $allowedMethods = $routeInfo[1];
                // ... 405 Method Not Allowed
                return ['msg' => 'Sem permissao para este metodo'];
                break;
            case FastRoute\Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];

                return $handler($vars);
                break;
        }
    }

}

print AdiantiRestServer::run($_REQUEST);
