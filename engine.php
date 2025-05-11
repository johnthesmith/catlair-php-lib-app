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
    Fork from pusa.dev https://gitlab.com/catlair/pusa/-/tree/main
*/



namespace catlair;



/*
    Application engine. Inherits from App.
    Extends the application with functionality for handling Payload modules.
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
                    '--{' . self::ID . '.method||method}=[METHOD]       | ' .
                    'Payload method',
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
        $payload = $this -> getParamMul([[ self::ID, 'payload' ], 'payload' ]);
        $method = $this -> getParamMul([[ self::ID, 'method' ], 'method' ]);

        $this -> validate
        (
            empty( $payload ),
            'engine-payload-not-found',
            [
                'message' => 'use --engine.payload cli argument'
            ]
        );

        $this -> validate
        (
            empty( $method ),
            'engine-method-not-found',
            [
                'message' => 'use --engine.method cli argument'
            ]
        );

        if( $this -> isOk() )
        {
            Payload::create
            (
                $this,
                $payload
            )
            -> run( $method )
            -> resultTo( $this );
        }

        $this -> getMon() -> flush();

        return $this;
    }




    /**************************************************************************
        Files path utils
    */
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
        $this -> getProjectPath
        (
            'payload',
            empty( $aProject ) ? null : $aProject
        )
        . $this -> getLocalPath( $aLocal );
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
            $aPayloadName . '.php',
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
        /* The name of the payload in the format any/path/payload */
        ? string $aPayload = '',
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
                    $aPayload,
                    $projectPath
                );
                $this -> getLog()
                -> trace( 'Looking at the librarby' )
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
            -> trace( 'Use library' )
            -> param( 'payload', $aPayload )
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
}
