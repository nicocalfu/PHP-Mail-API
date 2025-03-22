<?php
include_once("db.class.php");

//include_once $_SERVER['DOCUMENT_ROOT']."/config.php";  
class mysqlClass
{
   private $link;
   static $_instance;

   //Realiza la conexión a la base de datos.
   public function conectar()
   {
      $this->link = new mysqli(
         ConfiguracionBD::obtenerConfiguracion()["db_host"],
         ConfiguracionBD::obtenerConfiguracion()["db_user"],
         ConfiguracionBD::obtenerConfiguracion()["db_pass"],
         ConfiguracionBD::obtenerConfiguracion()["db_name"],
         $this-> link
      );
      $this->link->set_charset("utf8");
      return $this->link;
      // mysql_select_db($this->base_datos,$this->link);
      // @mysql_query("SET NAMES 'utf8'");
   }

   public function conectarVilkun()
   {
      $this->link = new mysqli(
         ConfiguracionBD::obtenerConfiguracion()["db_host"],
         ConfiguracionBD::obtenerConfiguracion()["db_user"],
         ConfiguracionBD::obtenerConfiguracion()["db_pass"],
         ConfiguracionBD::obtenerConfiguracion()["db_name"],
         $this->link
      );
      $this->link->set_charset("utf8");
      return $this->link;
   }

   public function conectarPortalProductor()
   {
      $this->link = new mysqli('localhost', 'private', 'private', 'private', $this->link);
      $this->link->set_charset("utf8");
      return $this->link;
   }
}