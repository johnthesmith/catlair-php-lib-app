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
    The payload provides the \catlair\Engine application with functionality
    to work with various file resources and states.

    Features:
        - defines the 'ro' directory (read-only)
        - defines the 'rw' directory (read-write)
        - provides an interface for encrypted storage

    Extends the base functionality of \catlair\Payload
*/



/* Include core utils */
require_once LIB . '/core/store_utils.php';

/* Include payload class for extending */
require_once 'payload.php';



/*
    Class of payload
*/
class Hub extends Payload
{
    /**************************************************************************
        Files path utils
    */

    /*
        Returns the path to the project's exchange directory.

        This directory may be used to store files written by the project.
        The application must have read and write permissions for this directory.
        It is recommended to add this directory to .gitignore.

        The directory should be used to store:
            - Logs
            - Temporary files
            - Caches
            - Monitoring data
            - etc.
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

        The directory should contain:
            - Templates
            - Scripts
            - Project source files
            - etc.
    */
    public function getRoPath
    (
        /* Additional path inside the 'ro' directory */
        string $aLocalPath = '',
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
        clLocalPath( $aLocalPath );
    }



    /*
        Returns the path for storing payload states
        ./rw/store/a/b/c/abc....bin.
        The file name is formed using the scatter name.
    */
    public function getStatePath
    (
        /* String of key name or array of strings */
        string | array $aPath
    )
    /* Filename of state */
    :string
    {
        /* Get current class name */
        $className = get_class( $this );

        /* Check aPath type */
        if( !is_array( $aPath ))
        {
            $aPath = [ $aPath ];
        }

        /* Add first element in to array - class name */
        array_unshift( $aPath, $className );

        /* Build file name */
        $file = clScatterName( sha256( implode( '-', $aPath )));

        /* Return filename with RW path */
        return $this -> getRwPath( 'store'. $file . '.bin' );
    }



    /**************************************************************************
        Utils
    */

    /*
        Checks if the file exists in the 'ro' directory.
    */
    public function roExists
    (
        /* local path without the leading '/' from the 'ro' folder */
        string $aFile
    )
    /* Return `true` for existing paths, `false` otherwice */
    :bool
    {
        return $this -> file_exists( getRoPath( $aFile ));
    }



    /*
        Reads the contents of a file from the 'ro' directory.
    */
    public function roRead
    (
        /* local path without the leading '/' from the 'ro' folder */
        string $a
    )
    /* File body */
    :string | false
    {
        $result = false;

        if( $this -> isOk())
        {
            $file = $this -> getRoPath( $aFile );

            /* Try read file if it exists */
            $result
            = $this -> roExists( $file )
            ? file_get_contents( $file )
            : false;

            if( $result === false )
            {
                $this -> setResult
                (
                    'payload-file-ro-read-error',
                    [ 'file-name' => $aFile ]
                );
            }
        }
        return $result;
    }



    /*
        Checks if the file exists in the 'rw' directory.
    */
    public function rwExists
    (
        /* local path without the leading '/' from the 'rw' folder */
        string $aFile
    )
    :bool
    {
        return $this -> file_exists( getRwPath( $aFile ));
    }



    /*
        Reads the contents of a file from the 'rw' directory.
    */
    public function rwRead
    (
        /* local path without the leading '/' from the 'rw' folder */
        string $aFile
    )
    :string | false
    {
        $result = false;

        if( $this -> isOk())
        {
            $file = $this -> getRoPath( $aFile );

            /* Try read file if it exists */
            $result
            = $this -> roExists( $file )
            ? file_get_contents( $file )
            : false;

            if( $result === false )
            {
                $this -> setResult
                (
                    'payload-file-ro-read-error',
                    [ 'file-name' => $aFile ]
                );
            }
        }

        return $result;
    }



    /*
        Reads the contents of a file in the 'rw' folder
    */
    public function rwWrite
    (
        /* local path without the leading '/' from the 'rw' folder */
        string $aFile,
        /* Content for writting */
        $aContent
    )
    :self
    {
        $file = $this -> getRwPath( $aFile );
        $path = dirname( $file );
        if( clCheckPath( dirname( $path )))
        {
            if( file_put_contents( $file, $aContent ) === false )
            {
                $this -> setResult
                (
                    'payload-rw-write-error',
                    [ 'file-name' => $aFile ]
                );
            }
        }
        else
        {
            $this -> setResult
            (
                'payload-rw-path-error',
                [ 'file-name' => $aFile ]
            );
        }
        return $this;
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
        /* Key name or path as an array of strings */
        string | array $aPath,
        /* Value to set */
        $aValue,
        /* Encryption key */
        ?string $aSSLKey            = null,
        /* Encryption method from openssl_get_cipher_methods() */
        string $aSSLMethod         = 'aes-256-cbc',
        /* Initialization vector length */
        int $aSSLVectorLength   = 16
    )
    {
        return $this -> resultFrom
        (
            clWriteStore
            (
                $this -> getStatePath( $aPath ),
                $aValue,
                $aSSLKey,
                $aSSLMethod,
                $aSSLVectorLength
            )
        );

        return $this;
    }



    /*
        Returns the value from the payload storage.
        States are stored within the application for the payload class.
    */
    public function getState
    (
        /* Key name or path as an array of strings */
        string | array $aPath,
        /* Default value if resul absent */
        $aDefault   = null,
        /* SSL encryption key */
        $aSSLKey    = null
    )
    {
        $result = null;
        $r = clReadStore
        (
            $result,
            $this -> getStorePath( $aPath ),
            $aDefault,
            $aSSLKey
        );
        return $result;
    }
}
