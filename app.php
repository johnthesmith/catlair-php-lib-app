<?php
/*
    Catlair PHP Copyright (C) 2021 https://itserv.ru

    This program (or part of program) is free software: you can redistribute
    it and/or modify it under the terms of the GNU Aferro General
    Public License as published by the Free Software Foundation,
    either version 3 of the License, or (at your option) any later version.

    This program (or part of program) is distributed in the hope that
    it will be useful, but WITHOUT ANY WARRANTY; without even the implied
    warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
    See the GNU Aferro General Public License for more details.
    You should have received a copy of the GNU Aferror General Public License
    along with this program. If not, see <https://www.gnu.org/licenses/>.
*/

/*
    Fork from pusa.dev https://gitlab.com/catlair/pusa/-/tree/main
*/

/*
    Application.

    Base class for creating Catlair applications, including services and console
    utilities. Implements command-line parameter (CLI) handling.

    WARNING !!!

        The module requires the definition of global constants.
            LIB - path to the Catlair libraries.
            ROOT - path to the root of the Catlair project.

    Info

        You can get information about the CLI attributes of the application in
        the help method or wrun the application with the --help flag.

    Example

        <?php
        namespace catlair;

        // Constants for library inclusion
        define( 'ROOT', __DIR__ );
        define( 'LIB', realpath( ROOT . '/../../lib' ));

        // Include the application class
        require_once LIB . '/app/app.php';

        // Create and run the application
        App::Create() -> run();

    still@itserv.ru
    igorptx@gmail.com
*/



namespace catlair;



/*
    Локальные библиотеки
*/
require_once LIB . '/core/log.php';
require_once LIB . '/core/mon.php';
require_once LIB . '/core/params.php';
require_once LIB . '/core/parse.php';



/*
    Returns a reference to the application. Can be used in the project as direct
    access to the application. References to all other objects must be accessed
    through Application. Adding similar functions is not recommended.

    Use strictly for debugging purposes only.
*/
function getApp()
{
    global $App;
    return $App;
}



/*
    Application class
*/
class App extends Params
{
    const ID = 'app';

    /*
        Application objects
    */

    /* Monitoring object */
    private $mon = null;
    /* Log object */
    private $log = null;
    /* Loaded classes list */
    private $loadedClasses = [];

    /*
        States of application
    */

    /*
        Application shutdown flag. True means the application should be stopped.
    */
    private $terminated         = false;

    /* The moment of the last update of the configuration file */
    private $lastFileUpdated    = 0;



    /*
        Constructor of the Application class.
    */
    public function __construct()
    {
        /* Creating the logging object */
        $this -> log = Log::create() -> trapBegin();

        /* Creating the monitoring object */
        $this -> mon = Mon::create( $this -> log );

        /*
            Configuration
            https://github.com/johnthesmith/scraps/blob/main/images/CatlairConf.jpg
        */
        $this

        /* Calling configuration procedures in child classes to implement client */
        -> onConfig()

        /* Retrieving parameters from the CLI */
        -> addParams( self::getCLI() )

        /* Retrieving parameters from the env */
        -> addParams( $_SERVER )

        /* Retrieving parameters from the configuration */
        -> configRead()
        ;

        /*
            Creating a reference to the application in a global variable.  Used
            in the getApp function strictly for debugging purposes.
        */
        global $app;
        $app = $this;

        $this
        -> getLog()
        -> start();
    }



    /*
        Creating the application
    */
    static public function create()
    :App
    {
        $result = new App();

        return $result;
    }



    /**************************************************************************
        Events
        Overridable configuration event for descendants

        create
            onConfig
            onHelp
        run
            onBeforeRun
            onRun
            onAfterRun
    */

    /*
        Configuration event — can be overridden in descendants
    */
    public function onConfig()
    :self
    {
        return $this;
    }

    /*
        Help output event — can be overridden in descendants
    */
    public function onHelp()
    :self
    {
        return $this;
    }

    /*
        Pre-run event — runs before main logic
    */
    public function onBeforeRun()
    :self
    {
        return $this;
    }

    /*
        Main application logic event
    */
    public function onRun()
    :self
    {
        $this -> setResult
        (
            'empty-application',
            [
                'message' =>
                'Your application does nothing. ' .
                'For useful work, you need to define the onRun ' .
                'method in a child class of the application.'
            ]
        );
        return $this;
    }

    /*
        Post-run event — runs after main logic
    */
    public function onAfterRun()
    :self
    {
        return $this;
    }



    /**************************************************************************
        Utils
    */



    /*
        Returns the list of CLI parameters as an associative array.
    */
    static private function getCli()
    :array
    {
        $result = [];

        if( isset( $_SERVER[ 'argv' ]))
        {
            $c = count( $_SERVER[ 'argv'] );
            for( $i = 1; $i < $c; $i ++ )
            {
                /*  Read the next parameter by index. */
                $param = $_SERVER[ 'argv'][ $i ];

                /* Split the key at the first equal sign. */
                $couple = explode( '=', $param, 2 );
                $count = count( $couple );

                /*     Get the key and value */
                $key    = $count > 0 ? $couple[ 0 ] : null;
                $value  = $count > 1 ? $couple[ 1 ] : true;

                /* Remove -- or - from the beginning of the key */
                $key = preg_replace( '/(^--|^-)/', '', $key);

                /*
                    Split the key by dot to obtain the hierarchy
                    and assign it to the result.
                */
                clValueToObject( $result, explode( '.', $key ), $value );
            }
        }
        return $result;
    }



    /*
        Returns true if the configuration file has been modified
    */
    public function configUpdated()
    :bool
    {
        $file = $this -> getConfigFileName();

        clearstatcache( false, $file );

        return
        $this -> lastFileUpdated != filemtime( $file );
    }



    /*
        Reads configuration file
    */
    public function configRead()
    {
        $configFile = $this -> getConfigFileName();

        $this
        -> getLog()
        -> trace( 'Reading configuration' )
        -> param( 'File name', $configFile )
        -> lineEnd();

        /* Check for the presence of the default file */
        if( empty( $configFile ) && file_exists( 'config.json' ) )
        {
            $configFile = 'config.json';
        }

        if( !empty( $configFile ))
        {
            if( !file_exists( $configFile ))
            {
                $this
                -> setResult
                (
                    'app-config-file-not-found',
                    [
                        'file' => $configFile,
                        'message' => 'Config file not found. Check the key --config'
                    ]
                )
                -> resultWarning();
            }
            else
            {
                /* Read cintent */
                $content = file_get_contents( $configFile );

                /* Parsing file */
                $config = clParse
                (
                    $content,
                    pathinfo( $configFile, PATHINFO_EXTENSION ),
                    $this
                );

                if( $this  -> isOk() )
                {
                    $this -> lastFileUpdated = filemtime( $configFile );
                    $this -> addParams( $config );
                    $this -> getLog() -> trace( 'Config readed' ) -> param( 'file', $configFile );
                    $this -> setOk();
                }
                else
                {
                    $this -> resultWarning();
                }
            }
        }

        /* Добираем параметры из из переменных окружения и cli */
        $cli = self::GetCLI();
        $this -> addParams( clArrayMerge( $_ENV,  $cli ));

        /* Устанавливаем режим вывода журнала */
        $logFile = $this -> getParamMul([[ 'app', 'log', 'file' ],'log' ]);

        if( empty( $logFile ))
        {
            /* Switch log to console */
            $this
            -> getLog()
            -> setDestination( Log::CONSOLE );
        }
        else
        {
            /* Switch log to file */
            $this
            -> getLog()
            -> setDestination( Log::FILE )
            -> setLogPath( dirname( $logFile ))
            -> setLogFile( basename( $logFile ));
        }

        /* Устанавливаем режим вывода журнала */
        $this -> getLog()
        -> setEnabled(      $this -> getParam([ 'app', 'log', 'enabled' ], true ))
        -> setTrapEnabled(  $this -> getParam([ 'app', 'log', 'trap' ], false ))
        -> setDebug(        $this -> getParam([ 'app', 'log', 'debug' ], true ))
        -> setTrace(        $this -> getParam([ 'app', 'log', 'trace' ], true ))
        -> setInfo(         $this -> getParam([ 'app', 'log', 'info' ], true ))
        -> setWarning(      $this -> getParam([ 'app', 'log', 'warning' ], true ))
        -> setError(        $this -> getParam([ 'app', 'log', 'error' ], true ))
        -> setJob(          $this -> getParam([ 'app', 'log', 'job' ], true ))
        -> setColored(      $this -> getParam([ 'app', 'log', 'colored' ], true ))
        -> setHeader(       $this -> getParam([ 'app', 'log', 'header' ], true ))
        -> setTree(         $this -> getParam([ 'app', 'log', 'tree' ], true ))
        -> setDumpExclude(  $this -> getParam([ 'app', 'log', 'dump-exclude' ], [] ))
        -> setTimeWarning(  $this -> getParam([ 'app', 'log', 'time-warning-mls' ], 500 ))
        ;

        /* Установка ключа мониторинга */
        $monitorFilePathName =  $this -> getParam([ 'app', 'monitor' ]);
        if( is_array( $monitorFilePathName ))
        {
            $MonitorFilePath = $this -> getParam([ 'app', 'monitor', 'path' ]);
            if( !empty( $monitorFilePath ))
            {
                $this -> getMon() -> setFilePath( $monitorFilePath );
            }
            $monitorFileName = $this -> getParam([ 'app', 'monitor', 'path' ]);
            if( !empty( $monitorFileName ))
            {
                $this -> getMon() -> setFileName( $monitorFilePath );
            }
        }
        else
        {
            if( !empty( $monitorFilePathName ))
            {
                $this -> getMon() -> setFilePathName( $monitorFilePathName );
            }
        }
        return $this;
    }



    /*
        Running the applicatio
    */
    public function run()
    {
        $this
        -> getLog()
        -> begin( $this -> getName() );

        /* Запуск системы помощи */
        if( $this -> paramExists( 'help' ))
        {
            $this -> help();
        }
        else
        {
            /* Начало запуска приложения */
            $this
            -> getLog()
            -> dump
            (
                $this -> getParams(),
                'Incoming parameters'
            );

            /* Запуск события onBeforeRun */
            $this -> onBeforeRun();

            if( !$this -> terminated )
            {
                /* Запуск специфичного кода потомков */
                $this -> onRun();
            }

            /* Запуск события onAfterRun */
            $this -> onAfterRun();
        }

        /* Вывод состояния если оно отличается от нормы */
        if( !$this -> isOk() )
        {
            $this -> getLog()
            -> warning( $this -> getCode() )
            -> param( 'code', $this -> getCode())
            -> Params( $this -> getDetails());
        }

        /* Завершение запуска приложения */
        $this
        -> getLog()
        -> end();

        if( $this -> getParam([ 'app', 'trace' ], true ))
        {
            $this
            -> getLog()
            -> traceTotal()
            -> statisticOut();
        }

        $this -> getLog() -> stop();

        if( $this -> getParam([ 'app', 'final-state' ], false ))
        {
            /* Return cli final state for application */
            print_r
            (
                json_encode
                (
                    $this -> getResultAsArray(),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) . PHP_EOL
            );
        }

        /* Set deathhand for application */
        if( !$this -> isOk() )
        {
            register_shutdown_function( fn() => exit( 1 ));
        }


        return $this;
    }



    /*
        Displays the help system.
        In addition to basic information, the descendant's onHelp event is triggered if available.
    */
    public function help()
    {
        /*  Turns off the log header */
        $this -> getLog()
        -> headerHide();

        /*  Displays standard information */
        $this -> getLog()
        -> prn
        (
            implode
            (
                PHP_EOL,
                [
                    '--help                         | Help information',
                    '--app.final-state=[true;false] | ' .
                    'Appliation returns the final state to STDOUT, default false',
                    '--app.name=[NAME]              | ' .
                    'Application name. This name must be unique for host or empty',
                    '--app.config=[FILE_PATH]       | ' .
                    'Config file name in JSON format. config.json is default config '.
                    'file will be read if exists',
                    '--app.log.file=[DESTINATION]   | '.
                    'Log destination. Empty value for console log or file name.',
                    '--app.log.enabled=[true;false] | '.
                    'true - the log is enabled, otherwise the log is disabled.',
                    '--app.log.trap=[true;false]    | '.
                    'true - the log trap is enabled, otherwise the log trap is ' .
                    'disabled, and all events will write to the log.',
                    '--app.log.debug=[true;false]   | ' .
                    'true - the log DEBUG messages are enabled, otherwise messages ' .
                    'are disabled',
                    '--app.log.trace=[true;false]   | '.
                    'true - the log TRACE messages are enabled, otherwise messages ' .
                    'are disabled',
                    '--app.log.info=[true;false]    | ' .
                    'true - the log INFO messages are enabled, otherwise messages ' .
                    'are disabled',
                    '--app.log.warning=[true;false] | ' .
                    'true - the log WARNING messages are enabled, otherwise ' .
                    'messages are disabled',
                    '--app.log.error=[true;false]   | ' .
                    'true - the log ERROR messages are enabled, otherwise messages ' .
                    'are disabled',
                    '--app.log.job=[true;false]     | '.
                    'true - the log hierarchy is enabled, otherwise it is disabled' ,
                    '--app.log.colored=[true;false] | '.
                    'true - the log colors are enabled, otherwise it is disabled',
                    '--app.log.tree=[true;false]    | ' .
                    'true - the log tree is enabled, otherwise it is disabled',
                    '--app.log.header=[true;false]  | ' .
                    'true - the log header for messages is enabled (time, lag etc), ' .
                    'otherwise it is disabled',
                    '--app.log.dump-exclude=[]      | ' .
                    'Array of strings with keys, from dump will be excluded from ' .
                    'the log, e.g. password' ,
                    '--app.log.time-warning-mls=[]  | ' .
                    'Warning time between two calls will be colored'
                ]
            ),
            'Application'
        );

        /*  Triggering descendant events */
        $this -> onHelp();

        /* Восстанавливаем заголовок */
        $this -> getLog() -> headerRestore();

        return $this;
    }



    /**************************************************************************
         Working with the logging system and states
    */


    /*
        Fast log the provided message
    */
    public function log
    (
        /* Arbitrary message to log */
        $aMessage
    )
    : self
    {
        $this -> getLog() -> prn( $aMessage );
        return $this;
    }



    /*
        Warning mesage to log
    */
    public function resultWarning
    (
        Result $aResult = null
    )
    {
        /* If no source is provided, the current application is used */
        if( empty( $aResult ))
        {
            $aResult = $this;
        }

        if( ! $aResult -> isOk() )
        {
            $this -> getLog()
            -> tracePush()
            -> setTrace( true )
            -> warning( $aResult -> getCode() )
            -> begin( 'Warning information' )
            -> dump( $aResult -> getDetails(), 'Details', null, '' )
            -> traceDump()
            -> tracePop()
            -> end();
        }
        return $this;
    }




    /*
        Error message to log
    */
    public function resultError
    (
        Result $aResult = null
    )
    :self
    {
        /* If no source is provided, the current application is used */
        if( empty( $aResult ))
        {
            $aResult = $this;
        }

        if( ! $aResult -> isOk() )
        {
            /* Outputs error information */
            $this -> getLog()
            -> tracePush()
            -> setTrace( true )
            -> error( $aResult -> getCode())
            -> begin( 'Error information' )
            -> trace()
            -> dump( $aResult -> getDetails(), 'Details', null, '' )
            -> traceDump()
            -> tracePop()
            -> end();
        }

        return $this;
    }



    /**************************************************************************
        Utils
    */


    /*
        Returns true if running in CLI
    */
    public function isCli()
    :bool
    {
        return php_sapi_name() == 'cli';
    }



    /*
        Returns true if running in FPM
    */
    public function isFpm()
    :bool
    {
        return php_sapi_name() == 'fpm-cgi' || php_sapi_name() == 'fpm-fcgi';
    }



    /*
        Загружает библиотеку, если она еще не загружена.
        Возвращает список новых классов, объявленных в библиотеке.
    */
    public function loadLibrary
    (
        /* File */
        $aFilePath,
        /* Result object */
        Result $result
    )
    /* List of classes */
    :array
    {
        /* Нормализуем имя файла*/
        $aFilePath = realpath( $aFilePath );

        /* Получаем список классов по файлу из массива */
        $classes = clValueFromObject
        (
            $this -> loadedClasses,
            $aFilePath,
            []
        );

        if( empty( $classes ))
        {
            /* Если список пуст ... */
            /* получаем список текущих классов */
            $prevClasses = get_declared_classes();
            /* грузим файл */
            if( file_exists( $aFilePath ))
            {
                /* Проверяем, загружен ли файл. Если нет, загружаем. */
                if( !in_array( $aFilePath, get_included_files()) )
                {
                    try
                    {
                        /* Loading the library */
                        require_once( $aFilePath );
                    }
                    catch( \Throwable $error )
                    {
                        $result -> setResult
                        (
                            'payload-library-load-error',
                            [
                                'library'   => $aFilePath,
                                'message'   => $error -> getMessage(),
                                'file'      => $error -> getFile(),
                                'line'      => $error -> getLine()
                            ]
                        );
                    }
                }
                $newClasses = get_declared_classes();
                $classes = array_values( array_diff( $newClasses, $prevClasses ));
                $this -> loadedClasses[ $aFilePath ] = $classes;
            }
            else
            {
                $this -> getLog()
                -> warning( 'library not found' )
                -> param( 'file', $aFilePath )
                -> lineEnd();
            }
        }
        return $classes;
    }


    /**************************************************************************
        File path utils
    */

    /*
        Return the config file name for application
    */
    public function getConfigFileName()
    :string
    {
        return $file = $this -> getParamMul
        (
            [
                [ 'app', 'config' ],
                'config'
            ],
            ''
        );
    }



    /*
        Returns the local path if available, or an empty value otherwise.
    */
    public function getLocalPath
    (
        /* Local path */
        string $aLocal = null
    )
    :string
    {
        return  empty( $aLocal ) ? '' : ( '/' . $aLocal );
    }




    /*
        Returns the local path of the project.
        Uses the global constant ROOT, which must be defined in the header file.
    */
    public function getProjectPath
    (
        /* Local path relative to the project */
        string $aLocal = null,
        /* Oribinal product path */
        string $aProjectPath = null
    )
    :string
    {
        return
        (
            $aProjectPath == null
            ? $this -> getParamMul([[ 'project', 'path' ], 'app-path' ], ROOT )
            : $aProjectPath
        ) .
        $this -> getLocalPath( $aLocal );
    }




    /**************************************************************************
        Setters and getters
    */

    /*
        Returns the logger object.
    */
    public function getLog()
    :Log
    {
        return $this -> log;
    }



    /*
        Возвращает объект мониторинга
    */
    public function getMon()
    :Mon
    {
        return $this -> mon;
    }



    /*
        Returns the application state, error message, and others
    */
    public function getState()
    : array
    {
        return
        [
            /* Application name */
            'application'   => $this -> getName(),
            /* State error code */
            'code'          => $this -> getCode(),
            /* Details */
            'detaile'       => $this -> getDetailes()
        ];
    }



    /*
        Sets the application name
    */
    public function setName
    (
        /* Application name */
        $aValue
    )
    : self
    {
        $this -> setParam( [ 'app', 'name' ], $aValue );
        return $this;
    }


    /*
        Returns the application name
    */
    public function getName()
    :string
    {
        return $this -> getParamMul
        (
            [[ 'app', 'name' ], 'name' ],
            'catlair application'
        );
    }
}
