<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    DB::statement($this->dropSP());
    DB::statement($this->createSP());
  }

  private function createSP()
  {
    return <<<SQL
            CREATE PROCEDURE `updateTaxpayersData` (IN `p_rut` varchar(15), IN `p_correo` varchar(100), IN `p_direccion_regional` varchar(255), IN `p_razon_social` varchar(255), IN `p_nro_resolucion` varchar(8), IN `p_fecha_resolucion` varchar(15))
            BEGIN
              
              #Variables
              DECLARE id_contribuyente      	int;
              DECLARE razon_social_cont     	varchar(255);
              DECLARE nro_resolucion_cont   	varchar(5);
              DECLARE fecha_resolucion_cont 	varchar(15);
              DECLARE direccion_regional_cont varchar(200);
              DECLARE correo_cont             varchar(100);

              DECLARE rut_numeros		   int;
              DECLARE digito_verificador varchar(1);

              #Verifico si el rut existe
              SET id_contribuyente = (SELECT id 
                            FROM contribuyentes
                            WHERE rut = p_rut);

              IF id_contribuyente IS NOT NULL THEN

                #Razon Social
                SET razon_social_cont = (SELECT razon_social from contribuyentes where id = id_contribuyente);

                IF razon_social_cont IS NULL OR razon_social_cont = '' THEN
                  UPDATE contribuyentes
                    SET razon_social = p_razon_social
                  WHERE id = id_contribuyente;
                END IF;

                #Nro Resolucion
                SET nro_resolucion_cont = (SELECT nro_resolucion from contribuyentes where id = id_contribuyente);

                IF nro_resolucion_cont IS NULL OR nro_resolucion_cont = '' THEN
                  UPDATE contribuyentes
                    SET nro_resolucion = p_nro_resolucion
                  WHERE id = id_contribuyente;
                END IF;

                #Fecha Resolucion
                SET fecha_resolucion_cont = (SELECT fecha_resolucion from contribuyentes where id = id_contribuyente);

                IF fecha_resolucion_cont IS NULL OR fecha_resolucion_cont = '' THEN
                  UPDATE contribuyentes
                    SET fecha_resolucion = p_fecha_resolucion
                  WHERE id = id_contribuyente;
                END IF;

                #Direccion Regional
                SET direccion_regional_cont = (SELECT direccion_regional from contribuyentes where id = id_contribuyente);

                IF direccion_regional_cont IS NULL OR direccion_regional_cont = '' THEN
                  UPDATE contribuyentes
                    SET direccion_regional = p_direccion_regional
                  WHERE id = id_contribuyente;
                END IF;

                #Correo
                SET correo_cont = (SELECT correo from contribuyentes where id = id_contribuyente);

                IF correo_cont IS NULL OR correo_cont = '' THEN
                  UPDATE contribuyentes
                    SET correo = p_correo
                  WHERE id = id_contribuyente;
                END IF;

              ELSE 

                #Aqui se separa el rut en dos partes
                  SET rut_numeros = CAST(SUBSTRING_INDEX(p_rut, '-', 1) AS UNSIGNED);
                SET digito_verificador = SUBSTRING_INDEX(p_rut, '-', -1);

                INSERT INTO contribuyentes (rut, dv, correo, direccion_regional, razon_social, nro_resolucion, fecha_resolucion)
                VALUES (rut_numeros, digito_verificador, p_correo, p_direccion_regional, p_razon_social, p_nro_resolucion, p_fecha_resolucion);

                SELECT "CONTRIBUYENTE CREADO";
              END IF;

            END;
        SQL;
  }

  private function dropSP()
  {
    return <<<SQL
            DROP PROCEDURE IF EXISTS `updateTaxpayersData`;
        SQL;
  }

  public function down()
  {
    return <<<SQL
            DROP PROCEDURE IF EXISTS `updateTaxpayersData`;
        SQL;
  }
};
