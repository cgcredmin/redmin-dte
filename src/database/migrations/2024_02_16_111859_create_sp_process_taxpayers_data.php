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

  private function createSp()
  {
    return <<<SQL
            CREATE PROCEDURE `redmin_dte`.`processTaxpayersData` (IN `p_limit` int)
            BEGIN

                #Cursor variables
                DECLARE c_rut 	 		   varchar(20);
                DECLARE c_razon_social 	   varchar(255);
                DECLARE c_nro_resolucion   varchar(10);
                DECLARE c_fecha_resolucion varchar(20);
                DECLARE c_correo 		   varchar(100);

                #Otras variables 
                DECLARE rut_numeros		   int;
                DECLARE digito_verificador varchar(1);
                DECLARE contador           int DEFAULT 0;

                DROP TABLE IF EXISTS temp_bulk_copy;
                CREATE TEMPORARY TABLE IF NOT EXISTS temp_bulk_copy (
                    rut              varchar(20),
                    digito_v         varchar(1),
                    razon_social     varchar(255),
                    nro_resolucion   varchar(50),
                    fecha_resolucion varchar(20),
                    correo           varchar(100)
                );

                #Construyo cursor
                BEGIN
                    DECLARE taxpayersCursor CURSOR FOR
                        SELECT BC.field_1 AS RUT
                            , BC.field_2 AS RAZON_SOCIAL
                            , BC.field_3 AS NRO_RESOLUCION
                            , BC.field_4 AS FECHA_RESOLUCION
                            , BC.field_5 AS CORREO 
                        FROM bulkCopyTable AS BC
                        WHERE field_1 REGEXP '^[0-9]{6,8}-[0-9kK]$'
                        AND BC.field_3 IS NOT NULL
                        AND BC.field_4 IS NOT NULL
                        AND BC.field_5 IS NOT NULL
                        AND BC.field_3 REGEXP '^[0-9]+$';

                    OPEN taxpayersCursor;
                        BEGIN
                            DECLARE fin_l bool default FALSE;
                            DECLARE continue HANDLER FOR NOT FOUND SET fin_l = TRUE; 

                            loop_taxprayers: LOOP
                                FETCH taxpayersCursor INTO c_rut, c_razon_social, c_nro_resolucion, c_fecha_resolucion, c_correo;
                                    IF fin_l THEN
                                        LEAVE loop_taxprayers;
                                    END IF;

                                    #Aqui se separa el rut en dos partes
                                    SET rut_numeros = CAST(SUBSTRING_INDEX(c_rut, '-', 1) AS UNSIGNED);
                                    SET digito_verificador = SUBSTRING_INDEX(c_rut, '-', -1);

                                    #Acá reemplazo posibles valores que induzcan problemas
                                    SET c_razon_social = REPLACE(c_razon_social, "'", "''");
                                    SET c_razon_social = REPLACE(c_razon_social, '"', '""');

                                    INSERT INTO temp_bulk_copy (rut, digito_v, razon_social, nro_resolucion, fecha_resolucion, correo)
                                    VALUES (rut_numeros, digito_verificador, c_razon_social, c_nro_resolucion, c_fecha_resolucion, c_correo);

                                    SET contador = contador + 1;

                                    IF contador >= 50000 THEN
                                        #Insertar todas las filas acumuladas en la tabla final
                                        INSERT INTO contribuyentes (rut, dv, razon_social, nro_resolucion, fecha_resolucion, direccion_regional, correo)
                                        SELECT rut, digito_v, razon_social, nro_resolucion, fecha_resolucion, '' as direccion_regional, correo
                                        FROM temp_bulk_copy;

                                        #Limpiar la tabla temporal
                                        DELETE FROM temp_bulk_copy;

                                        #Restablecer el contador de filas
                                        SET contador = 0;
                                    END IF;

                            END LOOP loop_taxprayers;

                            IF contador > 0 THEN
                                INSERT INTO contribuyentes (rut, dv, razon_social, nro_resolucion, fecha_resolucion, direccion_regional, correo)
                                SELECT rut, digito_v, razon_social, nro_resolucion, fecha_resolucion, '' as direccion_regional, correo
                                FROM temp_bulk_copy;
                            END IF;

                        END;
                    CLOSE taxpayersCursor;
                END;

            END;
        SQL;
  }

  private function dropSP()
  {
    return <<<SQL
            DROP PROCEDURE IF EXISTS `processTaxpayersData`;
        SQL;
  }

  public function down()
  {
    return <<<SQL
            DROP PROCEDURE IF EXISTS `processTaxpayersData`;
        SQL;
  }
};
