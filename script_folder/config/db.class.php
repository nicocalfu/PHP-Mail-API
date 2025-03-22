<?php
date_default_timezone_set('America/Santiago');
if (!class_exists('ConfiguracionBD')) {
    class ConfiguracionBD
    {
        private static $configuracion = array(
            'db_host' => "private",
            'db_name' => "private",
            'db_user' => "private",
            'db_pass' => "private"
        );

        public static function obtenerConfiguracion()
        {
            return self::$configuracion;
        }
    }
}

if (!class_exists('Configuracion_Apis')) {
    class Configuracion_Apis
    {
        private static $configuracion = array(
            'baseUrl' => "private",
            'exporter-token' => "private",
        );
        public static function obtenerConfiguracion()
        {
            return self::$configuracion;
        }
        public static function obtenerBaseUrl()
        {
            return self::$configuracion['baseUrl'];
        }
        public static function obtenerToken()
        {
            return self::$configuracion['exporter-token'];
        }
    }
}




if (!class_exists('Configuracion_Correo')) {
    class Configuracion_Correo
    {
        private static $configuracion = array(
            'CorreoDestinatario' => "private",
            'CorreoEmisor' => "private",
            'NombreEmisor' => "private",
            'Password' => 'private',

            'SMTPDebug' => 0,
            'SMTPAuth' => true,
            'SMTPSecure' => "tls",

            'Host' => "smtp.office365.com",
            'Port' => 587,

            'SMTPOptions_SSL_verify_peer' => false,
            'SMTPOptions_SSL_verify_peer_name' => false,
            'SMTPOptions_SSL_allow_self_signed' => true
        );

        public static function obtenerConfiguracion()
        {
            return self::$configuracion;
        }
    }
}
