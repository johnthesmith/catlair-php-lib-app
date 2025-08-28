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
    Refactoring from pusa.dev https://gitlab.com/catlair/pusa/-/tree/main
*/



namespace catlair;



/*
    The core payload provides the App application with functionality for dynamic
    loading of code modules and executing their methods.

    Payloads feature a mutation mechanism, allowing dynamic connection of new
    functionality with state transfer from parent to child and back.

    Functionality:
        creation of a payload;
        mutations;
        method execution;
        logging (inherited from the application);
        configuration (inherited from the application);
        monitoring (inherited from the application).
    Other functionality related to state and storage management is implemented
    in extensions — subclasses of Payload.
*/



require_once 'engine.php';



/*
    Class of payload
*/
class Payload extends Params
{
    /* Application object */
    private Engine | null $app      = null;

    /* Parent payload object during mutation and cloning */
    private Payload | null $parent  = null;

    /* Caller */
    private string | null $caller   = null;

    /*
        Payload module constructor
        Do not call this directly. Use the create method instead.
    */
    private function __construct
    (
        /* Application object */
        $aApp,
        /* Caller */
        string $aCaller = null,
        /* Parent for mutation */
        Payload | null $aParent = null,
    )
    {
        /* Set states */
        $this -> app = $aApp;
        $this -> parent = $aParent;
        $this -> caller = $aCaller;

        if( $this -> parent !== null )
        {
            $this -> resultFrom( $this -> parent );
        }
    }



    /*
        Call undefined method
    */
    public function __call
    (
        $aName,
        $aArguments
    )
    {
        return $this
        -> setResult
        (
            'payload-undefined-method',
            [
                'Method'    => $aName,
                'Class'     => get_class( $this )
            ]
        )
        -> backtrace()
        -> resultError();
    }



    /*
        Payload factory
        Returns a payload object of the specified class
        An optional parameter can be used
    */
    static public function create
    (
        /* The entine application object */
        $aApp,
        /*
            Payload route
            - UpperCamelCase
            - kebab-case
            - snake_case
        */
        string $aRoute = null,
        /* Caller id */
        string | null $aCaller = null,
        /* Parent payload for mutations */
        Payload $aParent = null
    )
    {
        /* Define result object */
        $result = new Result();

        /* Build route */
        $route = $aApp -> getRoute( $aRoute );

        if( empty( $route ))
        {
            /* Route not found */
            $result -> setResult
            (
                'route-not-found',
                [
                    'route' => $aRoute
                ]
            )
            -> backtrace();
            $aApp -> resultWarning( $result );
        }
        else
        {
            /* Route found */
            $aApp -> getLog() -> dump( $route, 'Final route' ) -> lineEnd();

            $libraryName = $route[ 'library'] ?? '';
            if( empty( $libraryName ))
            {
                $result -> setResult
                (
                    'payload-library-is-empty',
                    [
                        'route'     => $aRoute
                    ]
                )
                -> backtrace();
            }
            else
            {
                /* Retrive library name */
                $library = $aApp -> getPayloadFileAny( $libraryName );
                /* Loading library */
                if( empty( $library ))
                {
                    $result -> setResult
                    (
                        'payload-library-not-found',
                        [
                            'file'      => $library,
                            'payload'   => $aRoute
                        ]
                    )
                    -> backtrace();
                    /* Dump result in to log */
                    $aApp -> resultWarning( $result );
                }
                else
                {
                    $aApp -> loadLibrary( $library, $result );
                }
            }
        }

        if( $result -> isOk() )
        {
            if( class_exists( $route[ 'class'] ))
            {
                $aApp
                -> getLog()
                -> trace( 'Use class ' )
                -> param( 'name', $route[ 'class' ]);
            }
            else
            {
                $result -> setResult
                (
                    'payload-class-not-found',
                    [
                        'library'   => $library,
                        'payload'   => $aRoute,
                        'class'     => $route[ 'class' ]
                    ]
                )
                -> backtrace();
            }
        }

        /* Payload creation */
        if( $result -> isOk() )
        {
            /* Create requested payload */
            $payload = new $route[ 'class' ]( $aApp, $aCaller, $aParent );
        }
        else
        {
            /* Create default payload */
            $payload = new Payload( $aApp, $aCaller, $aParent );
        }


        if( $result -> isOk() )
        {
            /* Call event */
            $payload -> call( 'onCreate', [], true );
        }
        else
        {
            /* Add createion errors if exists */
            $payload -> mergeResultFrom( $result );
            /* Result to log */
            $payload -> resultWarning();
        }

        return $payload;
    }



    /*
       Создаёт новый объект на основе текущего Payload с полнaым
       клонированием состояния и вызовом.
    */
    public function copy()
    :self
    {
        $new = new static
        (
            $this->app,
            $this->caller,
            null
        );
        return clone $new;
    }



    /*
        Payload mutation
    */
    public function mutate
    (
        /* Class name to mutate into */
        string $aRoute,
        /* Caller */
        string $aCaller = null
    )
    {
        /* Create new payload */
        $result =
        self::create
        (
            $this -> getApp(),
            $aRoute,
            $aCaller === null ? $this -> caller : $aCaller,
            $this
        )
        /* Transfer attributes from the parent */
        -> copyFrom( $this )
        /* Call on mutate event */
        -> call( 'onMutate', [], true )
        ;

        return $result;
    }



    /*
        Restores the parent object from the mutated payload
    */
    public function unmutate()
    {
        $result = $this -> getParent();
        if( $result !== null )
        {
            $result
            /* Return parameters to parent */
            -> copyFrom( $this )
            /* Return result to parent */
            -> resultFrom( $this );
        }
        else
        {
            /* It was a parent */
            $result = $this;
        }
        return $result;
    }



    /*
        Direct call of the payload method by name
    */
    public function call
    (
        /* Method name */
        string          $aMethod,
        /* Additional arguments */
        array | null    $aArguments = [],
        /* Suppress the message about the method's absence */
        bool            $aSilent = false
    )
    {
        $aArguments ??= [];
        /* Replace - _ for the method name */
        $aMethod = str_replace( '-', '_', $aMethod );
        if( method_exists( $this, $aMethod ))
        {
            call_user_func_array
            (
                [ $this, $aMethod ],
                $this -> getMethodParameters( $aMethod, $aArguments )
            );
        }
        else
        {
            if( !$aSilent )
            {
                $this -> setResult
                (
                    'payload-method-does-not-exists',
                    [
                        'class' => get_class( $this ),
                        'method' => $aMethod
                    ],
                )
                -> resultWarning();
            }
        }

        return $this;
    }



    /*
        Executes a payload method.
        Execution is accompanied by calls to onBeforeRun and onAfterRun events.
    */
    public function run
    (
        /* Method name */
        string  $aMethod    = null,
        /* Additional arguments */
        array   $aArguments = []
    )
    {
        if( $this -> isOk() )
        {
            /* If method name is empty, default to 'onRun' */
            if( empty( $AMethod ))
            {
                $AMethod = 'onRun';
            }

             /* Call 'onBeforeRun' if it exists in the payload */
            $this -> call( 'onBeforeRun', [], true );

            if( $this -> isOk())
            {
                /* Call the specified method if it exists in the payload */
                $this -> call( $aMethod, $aArguments, false );
            }

            /* Call 'onAfterRun' if it exists in the payload */
            $this -> call( 'onAfterRun', [], true );
        }
        return $this;
    }



    /*
        Returns a list of values for the method's arguments.
        The source of the arguments is the application's parameter array.
        Additionally, a specific argument array may be added.
        The method performs transformation of parameter names.

            1. The method argument name will be converted into a hierarchical
            array for names containing __.
            2. The method will attempt to find arguments in camel_case format
            (e.g., my_argument), preserving the case of lowercase and uppercase
            letters.
            3. If that fails, it will try to read the argument in snake-case
            format (e.g., my-argument).
            4. If no value is found, the default value defined in the method
            will be returned.

        For example:

            1. a__b__c → ["a", "b", "c"]
            2.    a__b__c_d → ["a", "b", "c_d"] and then ["a", "b", "c-d"]
            3. A_B_C → ["A_B_C"] and then ["A-B-C"]
    */
    private function getMethodParameters
    (
        /* Method name */
        string $aMethod,
        /* Specific arguments */
        array $aArguments = []
    )
    {
        /* Prepare parameters */
        $method = new \ReflectionMethod( $this, $aMethod );
        $waitingParams = $method -> getParameters();
        $result = [];
        /* Build parametes source */
        $source = clArrayMerge
        (
            $this -> getApp() -> getParams(),
            $aArguments
        );

        /* Populating the list of expected method arguments */
        foreach( $waitingParams as $param )
        {
            /* Get name of argiment */
            $name = $param -> getName();
            /* Get type of argument */
            $type = (string) $param -> getType();

            /* Get default value */
            $default
            = $param -> isDefaultValueAvailable()
            ? $param -> getDefaultValue()
            : null;

            /* Get snake path */
            $snakePath = explode( '__', $param -> getName());
            /* Get value from source arguments */
            $value = clValueFromObject( $source, $snakePath, $default );

            if( $value === $default )
            {
                $kebabPath = array_map
                (
                    fn( $part ) => str_replace( '_', '-', $part ),
                    $snakePath
                );
                $value = clValueFromObject( $source, $kebabPath, $default );
            }

            /* Set value for name */
            $result[ $name ] = $this -> cast( $value, $type );
        }
        return $result;
    }


    /*
        Convert a value from any type to a custom type
    */
    private function cast
    (
        /* Value fto be conversion */
        $aValue,
        /* Custom type to convert the value to */
        $aType
    )
    {
        switch( $aType )
        {
            case 'int'      :return (int)    $aValue; break;
            case 'float'    :return (float)  $aValue; break;
            case '?string'  :return (string) $aValue; break;
            case 'string'   :return (string) $aValue; break;
            case 'bool'     :return (bool)   $aValue; break;
            case 'array'    :return (array)  $aValue; break;
            default         :return null; break;
        }
    }



    /**************************************************************************
        Setters and getters
    */

    /*
        Return the application object
    */
    public function getApp()
    {
        return $this -> app;
    }



    /*
        Return the log object
    */
    public function getLog()
    {
        return $this -> getApp() -> getLog();
    }



    /*
        Return the monitoring object of application
    */
    public function getMon
    (
        $aSetPayloadPath = false
    )
    {
        $mon = $this -> getApp() -> getMon();
        if( $aSetPayloadPath )
        {
            $mon -> setCurrentPath
            ([
                'payloads',
                get_class($this)
            ]);
        }
        return $mon;
    }



    /*
        Return parent of payload after mutation
    */
    public function getParent()
    {
        return $this -> parent;
    }



    /**************************************************************************
        Monitoring
    */

    /*
        Initial monitoring for payload
    */
    public function monBegin()
    {
        return $this->getMon(true)
        /* Mark the moment of the first launch in monitoring */
        ->now([ 'total', 'start' ], true)
        /* Mark the uptime since the first launch */
        ->deltaNow([ 'total', 'start' ], [ 'total', 'uptime' ])
        /* Mark the number of launches */
        ->add([ 'total', 'count' ])
        /* Mark the last launch moment */
        ->now([ 'last', 'moment' ])
        /* Start the execution time of the section */
        ->start([ 'last', 'duration-mls' ]);
    }



    /*
        Final monitoring for payload
    */
    public function monEnd()
    {
        /* Moment of request completion */
        return $this
        ->getMon(true)
        ->stop([ 'last', 'duration-mls' ], Moment::MILLISECOND)
        ->min([ 'total', 'min-duration-mls' ], [ 'last', 'duration-mls' ])
        ->max([ 'total', 'max-duration-mls' ], [ 'last', 'duration-mls' ])
        ->sum([ 'total', 'duration-mls' ], [ 'last', 'duration-mls' ])
        ->avg
        (
            [ 'total', 'avg-duration-mls' ],
            [ 'total', 'duration-mls' ],
            [ 'total', 'count' ]
        );
    }



    /**************************************************************************
        Files path utils
    */
    /*
        Return path to payloads libraries
        PROJECT/payload/local....
    */
    static public function getPayloadPath
    (
        /* Application */
        Engine $aApp,
        /* Local path from payload directory */
        string $aLocal      = null,
        /* Optional specific project */
        string $aProject    = null,
    )
    :string
    {
        return
        $aApp -> getProjectPath
        (
            'payload',
            empty( $aProject ) ? null : $aProject
        ) .
        clLocalPath( $aLocal );
    }



    /*
        Returns the payload file by its name
        PROJECT/payload/any/path/payload.php.
    */
    static public function getPayloadFile
    (
        /* Application */
        Engine $aApp,
        /* Payload name in the format any/path/payload */
        string $aPayloadName    = null,
        /* Path to the project */
        string $aProject        = null
    )
    :string
    {
        return self::getPayloadPath
        (
            $aApp,
            $aPayloadName,
            $aProject
        );
    }



    /*
        Retrieves the path to a payload file.
        If it is not found in the current project, it falls back to the default project.
        Returns false if the file does not exist in either.

        This function is called directly from the Payload module.
        It is not recommended to call it from other places.
    */
    static public function getPayloadFileAny
    (
        /* Application */
        App $aApp,
        /* Payload name in the format any/path/payload */
        ? string $aPayload = '',
    )
    {
        /* Запрос перечня проектов */
        $projects = $aApp -> getProjects( $aApp );
        foreach( $projects as $projectPath )
        {
            if( !empty( $projectPath ))
            {
                /* Return default ptoject path */
                $lib = self::getPayloadFile
                (
                    $aApp,
                    $aPayload,
                    $projectPath
                );
                $aApp -> getLog()
                -> trace( 'Looking at the librarby' )
                -> param( 'path', $lib);
                $result = realpath( $lib );
                if( !empty( $result ))
                {
                    break;
                }
            }
        }

        if( !empty( $result ))
        {
            $aApp -> getLog()
            -> trace( 'Use library' )
            -> param( 'payload', $aPayload )
            -> param( 'file', $result );
        }

        return $result;
    }




    /**************************************************************************
        Utils
    */


    /*
        Set an error if not running in CLI
    */
    public function cliOnly()
    {
        if( !$this -> getApp() -> isCli() )
        {
            $this -> setResult( 'payload-cli-only' ) -> backtrace();
        }
        return $this;
    }



    /*
        Check caller for payload
    */
    public function checkCaller
    (
        string $aCaller
    )
    :self
    {
        if( $this -> getCaller() !== $aCaller )
        {
            $this -> setResult
            (
                'payload-caller-invalid',
                [
                    'payload-caller'  => $this -> caller,
                    'requested-caller' => $aCaller
                ]
            )
            -> backtrace();
            /* Dump result in to log */
            $this -> resultWarning( $this );
        }
        return $this;
    }



    /*
        The state will be logged as a warning
        Wrapper around the App method
    */
    public function resultWarning()
    {
        $this -> getApp() -> resultWarning( $this );
        return $this;
    }



    /*
        The state will be logged as an error
        Wrapper around the App method
    */
    public function resultError()
    {
        $this -> getApp() -> resultError( $this );
        return $this;
    }



    /*
        Return project path from app
    */
    public function getProjectPath
    (
        /* Local path relative to the project */
        string $aLocal = null,
        /* Oribinal product path */
        string $aProjectPath = null
    )
    {
        return $this -> getApp() -> getProjectPath( $aLocal, $aProjectPath );
    }



    /*
        Return caller
    */
    public function getCaller()
    :string | null
    {
        return $this -> caller;
    }



    /*
        Set caller
    */
    public function setCaller
    (
        string $a
    )
    :self
    {
        $this -> caller = $a;
        return $this;
    }
}
