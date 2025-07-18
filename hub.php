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
        return $this -> getApp() -> getRwPath( $aLocal, $aProjectPath );
    }



    /*
        Returns the path to the project's file storage.
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
        return $this -> getApp() -> getRoPath( $aLocal, $aProjectPath );
    }



    /*
        Returns the path to the project's exchange directory.
    */
    public function getRwPrivatePath
    (
        /* Additional path inside the 'rw' directory */
        string $aLocal = '',
        /* Project root directory */
        string $aProjectPath = null
    )
    :string
    {
        return $this -> getApp() -> getRwPrivatePath( $aLocal, $aProjectPath );
    }



    /*
        Returns the path to the project's exchange directory.
    */
    public function getRwPublicPath
    (
        /* Additional path inside the 'rw' directory */
        string $aLocal = '',
        /* Project root directory */
        string $aProjectPath = null
    )
    :string
    {
        return $this -> getApp() -> getRwPublicPath( $aLocal, $aProjectPath );
    }



    /*
        Returns the path to the project's file storage.
    */
    public function getRoPrivatePath
    (
        /* Additional path inside the 'ro' directory */
        string $aLocal = '',
        /* Project root directory */
        string $aProjectPath  = null
    )
    :string
    {
        return $this -> getApp() -> getRoPrivatePath( $aLocal, $aProjectPath );
    }



    /*
        Returns the path to the project's file storage.
    */
    public function getRoPublicPath
    (
        /* Additional path inside the 'ro' directory */
        string $aLocal = '',
        /* Project root directory */
        string $aProjectPath  = null
    )
    :string
    {
        return $this -> getApp() -> getRoPublicPath( $aLocal, $aProjectPath );
    }



    /*
        Returns the path for storing payload states
    */
    public function getStatePath
    (
        /* String of key name or array of strings */
        string | array $aPath
    )
    /* Filename of state */
    :string
    {
        return $this -> getApp() -> getStatePath( $this, $aPath );
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
        if( $this -> isOk() )
        {
            $this -> getApp() -> setState
            (
                $this,
                $aPath,
                $aValue,
                $aSSLKey,
                $aSSLMethod,
                $aSSLVectorLength
            ) -> resultTo( $this );
        }
        return $this;
    }



    /*
        Sets the value in the payload storage.
        States are stored within the application for the payload class.
    */
    public function setStateShare
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
        if( $this -> isOk() )
        {
            $this -> getApp() -> setState
            (
                null,
                $aPath,
                $aValue,
                $aSSLKey,
                $aSSLMethod,
                $aSSLVectorLength
            ) -> resultTo( $this );
        }
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
        return $this -> getApp() -> getState
        (
            $this,
            $aPath,
            $aDefault,
            $aSSLKey
        );
    }



    /*
        Returns the value from the payload storage.
        States are stored within the application for the payload class.
    */
    public function getStateShare
    (
        /* Key name or path as an array of strings */
        string | array $aPath,
        /* Default value if resul absent */
        $aDefault   = null,
        /* SSL encryption key */
        $aSSLKey    = null
    )
    {
        return $this -> getApp() -> getState
        (
            null,
            $aPath,
            $aDefault,
            $aSSLKey
        );
    }

}
