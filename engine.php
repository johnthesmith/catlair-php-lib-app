<?php
/*
    Catlair PHP Copyright (C) 2021 https://itserv.ru

    This program (or part of program) is free software: you can redistribute it
    and/or modify it under the terms of the GNU Aferro General Public License as
    published by the Free Software Foundation, either version 3 of the License,
    or (at your option) any later version.

    This program (or part of program) is distributed in the hope that it will be
    useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Aferro
    General Public License for more details. You should have received a copy of
    the GNU Aferror General Public License along with this program. If not, see
    <https://www.gnu.org/licenses/>.
*/

/*
    Fork and full refactoring
    from pusa.dev https://gitlab.com/catlair/pusa/-/tree/main
*/



namespace catlair;



/*
    Application engine. Inherits from App.
    Extends the application with functionality for handling Payload modules.

    # Payload Usage Instructions

    1. **Create PHP payload class**
       - Place your class file in the appropriate payload directory.
       - Ensure class name and namespace match the route definition.
       - Implement necessary methods (e.g., `onCreate`, custom methods).

    2. **Define route configuration**
       - Create route YAML file with keys: `library`, `class`, `method` (optional), etc.
       - Use flat or nested route naming (e.g., `path/to/route` or `flat-route`).
       - Place method name if you want it invoked automatically during payload creation.

    3. **Create payload instance in code**
       - Call `Payload::create($engineApp, 'route-name')`.
       - The method from the route will be called automatically if defined.
       - Use chaining or call additional methods on returned payload object as needed.
*/



require_once 'app.php';
require_once 'payload.php';



/*
    Class engine
*/
class Engine extends App
{
    /* Engine id for configuration and information */
    const ID = 'engine';

    /* Default project */
    const PROJECTS = [ '.', '../default' ];

    /* Default payload route */
    const ROUTE_DEFAULT =
    [
        'library' => 'default.php',
        'class' => '/catlair/Default',
        'method' => '',
        'query' => []
    ];

    /*
        Create Engine Appllication object
    */
    static public function create()
    :self
    {
        return new Engine();
    }



    /**************************************************************************
        Events
    */



    /*
        Additional information for the help system
    */
    public function onHelp()
    :self
    {
        parent::onHelp();

        $this -> getLog()
        -> prn
        (
            implode
            (
                PHP_EOL,
                [
                    '--{' . self::ID . '.payload||payload}=[PAYLOAD]    | ' .
                    'Payload module for running ' ,
                    '--' . self::ID . '.projects=path/project1;...      | ' .
                    'List of project paths for searching ' .
                    'components in the current project. Default value is ' .
                    implode( ';', self::PROJECTS )

                ]
            ),

            'Engine information'
        );

        return $this;
    }



    /*
        On application run event
        Retrieve the payload from the argument, load the payload, and run the method
    */
    public function onRun()
    :self
    {
        $payload = $this -> getParam([ self::ID, 'payload' ]);

        $this -> validate
        (
            empty( $payload ),
            'engine-payload-not-found',
            [
                'message' => 'use --engine.payload cli argument'
            ]
        );

        if( $this -> isOk() )
        {
            Payload::create( $this, $payload ) -> resultTo( $this );
        }

        $this -> getMon() -> flush();

        return $this;
    }




    /**************************************************************************
        Files path utils
        Main structire
            root/
                rw/
                    private/
                        log
                        cache
                    public/
                        user-data
                ro/
                    private/
                        scripts
                    public/
                        content
                        file
    */


    /*
        Returns the path to the project's exchange directory.

        This directory may be used to store files written by the project.
        The application must have read and write permissions for this directory.
        It is recommended to add this directory to .gitignore.
    */
    public function getRwPath
    (
        /* Additional path inside the 'rw' directory */
        string $aLocal = '',
        /* Project root directory */
        string $aProjectPath = null
    )
    :string
    {
        return
        $this -> getProjectPath
        (
            'rw',
            empty( $aProjectPath ) ? null : $aProjectPath
        ) .
        clLocalPath( $aLocal );
    }



    /*
        Returns the path to the project's file storage.

        This directory contains files that are guaranteed to be preserved
        throughout the project's lifecycle. The application is expected to
        have read-only access to this directory.
    */
    public function getRoPath
    (
        /* Additional path inside the 'ro' directory */
        string $aLocal = '',
        /* Project root directory */
        string $aProjectPath  = null
    )
    :string
    {
        return
        $this -> getProjectPath
        (
            'ro', empty( $aProjectPath ) ? null : $aProjectPath
        ) .
        clLocalPath( $aLocal );
    }



    /*
    	Returns the path to the project's rw/public directory.

    	This directory is used for public files with read-write access.
    	It is recommended to add this directory to .gitignore.
    */
    public function getRwPublicPath
    (
    	string $aLocal = '',
    	string $aProjectPath = null
    )
    :string
    {
    	return
    	$this -> getRwPath( 'public' . clLocalPath( $aLocal ), $aProjectPath );
    }



    /*
    	Returns the path to the project's rw/private directory.

    	This directory is used for private files with read-write access.
    	It is recommended to add this directory to .gitignore.
    */
    public function getRwPrivatePath
    (
    	string $aLocal = '',
    	string $aProjectPath = null
    )
    :string
    {
    	return
    	$this -> getRwPath( 'private' . clLocalPath( $aLocal ), $aProjectPath );
    }




    /*
        Returns the path for storing payload states
        ./rw/store/a/b/c/abc....bin.
        The file name is formed using the scatter name.
    */
    public function getStatePath
    (
        /* Payload class */
        Payload | null $payload,
        /* String of key name or array of strings */
        string | array $aPath
    )
    /* Filename of state */
    :string
    {
        /* Check aPath type */
        if( !is_array( $aPath ))
        {
            $aPath = [ $aPath ];
        }

        /* Add first element in to array - class name */
        array_unshift
        (
            $aPath,
            empty( $payload ) ? 'null' : get_class( $payload )
        );

        /* Build file name */
        $file = clScatterName( hash('sha256', implode( '-', $aPath )));

        /* Return filename with RW path */
        return $this -> getRwPrivatePath
        (
            'store' . clLocalPath( $file . '.bin' )
        );
    }



    /*
        Return logs path
        PROJECT/rw/private/logs/local...
    */
    public function getLogsPath
    (
        /* Local path from payload directory */
        string $aLocal      = null,
        /* Optional specific project */
        string $aProject    = null,
    )
    :string
    {
        return
        $this -> getRwPrivatePath
        (
            'logs',
            empty( $aProject ) ? null : $aProject
        )


        TODO
        . clLocalPath( $aLocal );
    }



    /*
        Return path to payloads libraries
        PROJECT/payload/local...
    */
    public function getPayloadPath
    (
        /* Local path from payload directory */
        string $aLocal      = null,
        /* Optional specific project */
        string $aProject    = null,
    )
    :string
    {
        return
        $this -> getRoPath
        (
            'payload',
            empty( $aProject ) ? null : $aProject
        )
        . clLocalPath( $aLocal );
    }


    /*
        Returns the payload file by its name
        PROJECT/payload/any/path/payload.php.
    */
    public function getPayloadFile
    (
        /* Payload name in the form of any/path/payload */
        string $aPayloadName    = null,
        /* Path to the project */
        string $aProject        = null
    )
    :string
    {
        return $this -> getPayloadPath
        (
            $aPayloadName,
            $aProject
        );
    }



    /*
        Retrieves the payload path.
        A sequential search is performed based on the project list.
        If the payload is not found, it returns false.

        This function is called directly from the Payload module.
        It is not recommended to call it from other locations.
    */
    public function getPayloadFileAny
    (
        /* The name of the payload file */
        ? string $aPayloadFile = '',
    )
    {
        /* Запрос перечня проектов */
        $projects = $this -> getProjects();
        foreach( $projects as $projectPath )
        {
            if( !empty( $projectPath ))
            {
                /* Return default ptoject path */
                $lib = self::getPayloadFile
                (
                    $aPayloadFile,
                    $projectPath
                );
                $this -> getLog()
                -> trace( 'Looking for library' )
                -> param( 'path', $lib );
                $result = realpath( $lib );
                if( !empty( $result ))
                {
                    break;
                }
            }
        }

        if( !empty( $result ))
        {
            $this -> getLog()
            -> trace( 'Found library' )
            -> param( 'payloadFile', $aPayloadFile )
            -> param( 'realFile', $result );
        }

        return $result;
    }



    /*
        Return path to route folder
        PROJECT/ro/router/local...
    */
    public function getRouterPath
    (
        /* Local path from router directory */
        string $aLocal      = null,
        /* Optional specific project */
        string $aProject    = null,
    )
    :string
    {
        return
        $this -> getRoPath( 'router', $aProject ?: null )
        . clLocalPath( $aLocal );
    }



    /*
        Retrieves the route path
        A sequential search is performed based on the project list.
        If the payload is not found, it returns false.
    */
    public function getRouteFileAny
    (
        /* The name of the payload in the format any/path/payload */
        ? string $aPath = '',
    )
    {
        /* Запрос перечня проектов */
        $projects = $this -> getProjects();
        foreach( $projects as $projectPath )
        {
            if( !empty( $projectPath ))
            {
                /* Return default ptoject path */
                $file = self::getRouterPath( $aPath, $projectPath );
                $this -> getLog()
                -> trace( 'Looking for route' )
                -> param( 'path', $file )
                -> lineEnd();
                $result = realpath( $file );
                if( !empty( $result ))
                {
                    break;
                }
            }
        }

        if( !empty( $result ))
        {
            $this -> getLog()
            -> trace( 'Found route' )
            -> param( 'file', $result );
        }

        return $result;
    }


    /**************************************************************************
        Setters and getters
    */

    /*
        Sets the project paths for retrieving components
        that are missing in the main project.
    */
    public function setProjects
    (
        array $a
    )
    :self
    {
        $this
        -> setParam([ self::ID, '.projects' ], $a );
        return $this;
    }



    /*
        Returns the list of default projects for retrieving components
        that are missing in the main project.
    */
    public function getProjects()
    :array
    {
        $result = $this -> getParam
        (
            [ self::ID, 'projects' ],
            self::PROJECTS
        );

        switch( gettype( $result ))
        {
            /* Converts from string */
            case 'string': $result = explode( ';', $result ); break;
            /* Uses array of string */
            case 'array': break;
            /* Uses default value */
            default: $result = self::PROJECTS; break;
        }
        return $result;
    }



    /*
        Returns the list of payloads
    */
    public function getPayloads()
    {
        $payloads = [];

        foreach( $this -> getProjects() as $projectPath )
        {
            $path = Payload::getPayloadPath( $this, '', $projectPath );
            /* Started scanning files */
            clFileScan
            (
                $path,
                null,
                function( $aFile ) use ( &$payloads, $path )
                {
                    /* If the file is PHP... */
                    if
                    (
                        strtolower( pathinfo( $aFile, PATHINFO_EXTENSION)) == 'php'
                    )
                    {
                        $value = explode
                        (
                            '.',
                            str_replace( $path . '/', '', $aFile ),
                            2
                        )[ 0 ];
                        if( !in_array( $value, $payloads ))
                        {
                            /* Add to the list of payloads */
                            $payloads[] = $value;
                        }
                    }
                }
            );
        }

        /* Returned the list of payloads */
        return $payloads;
    }



    /**************************************************************************
        Storage functionality for payload states
    */

    /*
        Sets the value in the payload storage.
        States are stored within the application for the payload class.
    */
    public function setState
    (
        /* Payload or null */
        Payload | null $aPayload,
        /* Key name or path as an array of strings */
        string | array $aPath,
        /* Value to set */
        $aValue,
        /* Encryption key */
        string | null $aSSLKey  = null,
        /* Encryption method from openssl_get_cipher_methods() */
        string $aSSLMethod      = 'aes-256-cbc',
        /* Initialization vector length */
        int $aSSLVectorLength   = 16
    )
    {
        return clWriteStore
        (
            $this -> getStatePath( $aPayload, $aPath ),
            $aValue,
            $aSSLKey,
            $aSSLMethod,
            $aSSLVectorLength
        );
    }



    /*
        Returns the value from the payload storage.
        States are stored within the application for the payload class.
    */
    public function getState
    (
        /* Payload or null */
        Payload|null $aPayload,
        /* Key name or path as an array of strings */
        string | array $aPath,
        /* Default value if resul absent */
        $aDefault   = null,
        /* SSL encryption key */
        $aSSLKey    = null
    )
    {
        $value = null;

        clReadStore
        (
            $value,
            $this -> getStatePath( $aPayload, $aPath ),
            $aDefault,
            $aSSLKey
        );
        return $value;
    }




    /*
        Return payload route array

        Each route key will be obtained sequentially
        from the following sources, if they exist:

            1. iterating through the list of projects to check for a route file
            2. reading the default route from the configuration key
            engine.default.route
            3. falling back to self::ROUTE_DEFAULT
    */
    public function getRoute
    (
        /* Route name with delimiter `/` */
        string $aPayloadName
    )
    /* Route array */
    :array
    {
        $result = [];

        /* Check empty route */
        if( empty( $aPayloadName ))
        {
            $aPayloadName = $this -> getParam
            (
                [ 'engine', 'default', 'route-name' ],
                'default'
            );
        }

        $full = explode( '/', $aPayloadName );

        /* Extract head element of path */
        $head = $full[0] ?? null;
        $file = $this -> getRouteFileAny( $head . '.yaml' );

        if( $file === false && count( $full ) > 1 )
        {
            $file = $this -> getRouteFileAny( $aPayloadName . '.yaml' );
        }

        if( $file !== false )
        {
            $result = clParse( @file_get_contents( $file ), 'yaml', $this );
        }
        else
        {
            $this
            -> getLog()
            -> trace( 'Payload route not found' )
            -> param( 'payload', $aPayloadName )
            -> lineEnd();
        }

        /* Build route from sources */
        $result
        = $result
        + $this -> getParam( [ 'engine', 'default', 'route' ], [] )
        + self::ROUTE_DEFAULT;

        return ( $result[ 'enabled' ] ?? true ) ? $result : [];
    }
}

