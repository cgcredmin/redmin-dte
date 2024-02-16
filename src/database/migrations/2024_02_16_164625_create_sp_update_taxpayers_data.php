<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

    private function createSP(){
        return <<<SQL
            CREATE PROCEDURE `updateTaxpayersData` (IN `p_rut` varchar(15), IN `p_correo` varchar(100), IN `p_direccion_regional` varchar(255), IN `p_razon_social` varchar(255),IN `p_nro_resolucion` varchar(8),IN `p_fecha_resolucion` varchar(15))
            BEGIN
                
                #Variables
                DECLARE id_contribuyente int;

                #Verifico si el rut existe
                SET id_contribuyente = (SELECT id 
                                        FROM contribuyentes
                                        WHERE rut = p_rut);

                IF id_contribuyente IS NOT NULL THEN
                    UPDATE contribuyentes 
                    SET razon_social = p_razon_social
                        , nro_resolucion = p_nro_resolucion
                        , fecha_resolucion = p_fecha_resolucion
                        , direccion_regional = p_direccion_regional
                        , correo = p_correo
                    WHERE id = id_contribuyente;

                ELSE 
                    SELECT "EL RUT INGRESADO NO EXISTE EN EL REGISTRO DE CONTRIBUYENTES";
                END IF;

            END;    
        SQL;
    }

    private function dropSP(){
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