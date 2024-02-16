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
            CREATE PROCEDURE `getTaxpayersData` (IN `p_limit` int)
            BEGIN
    
                #Cursor variables
                DECLARE c_field_1 varchar(255);
                DECLARE c_field_2 varchar(255);
                DECLARE c_field_3 varchar(255);
                DECLARE c_field_4 varchar(255);
                DECLARE c_field_5 varchar(255);
                DECLARE c_field_6 varchar(255);
                DECLARE c_field_7 varchar(255);
                DECLARE c_field_8 varchar(255);
                DECLARE c_field_9 varchar(255);
                DECLARE c_field_10 varchar(255);
                DECLARE c_id      int;
    
                #Otras variables 
                DECLARE rut 		 varchar(20);
                DECLARE rutFound     BOOL DEFAULT FALSE; 
                DECLARE email	     varchar(100);
                DECLARE emailFound   BOOL DEFAULT FALSE;
    
                #Expresiones regulares
                DECLARE rutRegExp 	varchar(50)  DEFAULT '^[0-9]{6,8}-[0-9kK]$';
                DECLARE emailRegExp varchar(100) DEFAULT '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}$';
    
                DROP TABLE IF EXISTS temp_wrong_data;
                CREATE TEMPORARY TABLE IF NOT EXISTS temp_wrong_data AS (
                    SELECT BC.field_1 AS field_1
                        , BC.field_2 AS field_2
                        , BC.field_3 AS field_3
                        , BC.field_4 AS field_4
                        , BC.field_5 AS field_5
                        , BC.field_6 AS field_6
                        , BC.field_7 AS field_7
                        , BC.field_8 AS field_8
                        , BC.field_9 AS field_9
                        , BC.field_10 AS field_10
                        , id
                    FROM bulkCopyTable AS BC
                    WHERE field_1 NOT REGEXP rutRegExp
                        OR field_3 IS NULL 
                        OR field_4 IS NULL 
                        OR field_5 IS NULL
                        OR field_3 NOT REGEXP '^[0-9]+$'
                );
    
                #Construyo cursor
                BEGIN
                    DECLARE taxpayersCursor CURSOR FOR
                        SELECT WD.field_1 
                            , WD.field_2 
                            , WD.field_3 
                            , WD.field_4 
                            , WD.field_5 
                            , WD.field_6
                            , WD.field_7
                            , WD.field_8
                            , WD.field_9
                            , WD.field_10
                            , id
                        FROM temp_wrong_data AS WD
                        WHERE WD.field_2 IS NOT NULL;
    
                    OPEN taxpayersCursor;
                        BEGIN
                            DECLARE fin_l bool default FALSE;
                            DECLARE continue HANDLER FOR NOT FOUND SET fin_l = TRUE; 
    
                            loop_taxprayers: LOOP
                                FETCH taxpayersCursor INTO c_field_1, c_field_2, c_field_3, c_field_4, c_field_5
                                                        , c_field_6, c_field_7, c_field_8, c_field_9, c_field_10, c_id;
                                    IF fin_l THEN
                                        LEAVE loop_taxprayers;
                                    END IF;
    
                                    #Busco rut del contribuyente
                                    IF c_field_1 REGEXP rutRegExp THEN
                                        SET rut = c_field_1;
                                        SET rutFound = TRUE;
                                    ELSEIF c_field_2 REGEXP rutRegExp THEN
                                        SET rut = c_field_2;
                                        SET rutFound = TRUE;
                                    ELSEIF c_field_3 REGEXP rutRegExp THEN
                                        SET rut = c_field_3;
                                        SET rutFound = TRUE;
                                    ELSEIF c_field_4 REGEXP rutRegExp THEN
                                        SET rut = c_field_4;
                                        SET rutFound = TRUE;
                                    ELSEIF c_field_5 REGEXP rutRegExp THEN
                                        SET rut = c_field_5;
                                        SET rutFound = TRUE;
                                    ELSEIF c_field_6 REGEXP rutRegExp THEN
                                        SET rut = c_field_6;
                                        SET rutFound = TRUE;
                                    ELSEIF c_field_7 REGEXP rutRegExp THEN
                                        SET rut = c_field_7;
                                        SET rutFound = TRUE;
                                    ELSEIF c_field_8 REGEXP rutRegExp THEN
                                        SET rut = c_field_8;
                                        SET rutFound = TRUE;
                                    ELSEIF c_field_9 REGEXP rutRegExp THEN
                                        SET rut = c_field_9;
                                        SET rutFound = TRUE;
                                    ELSEIF c_field_10 REGEXP rutRegExp THEN
                                        SET rut = c_field_10;
                                        SET rutFound = TRUE;
                                    END IF;
    
                                    #Busco correo del contribuyente
                                    IF c_field_1 REGEXP emailRegExp THEN
                                        SET email = c_field_1;
                                        SET emailFound = TRUE;
                                    ELSEIF c_field_2 REGEXP emailRegExp THEN
                                        SET email = c_field_2;
                                        SET emailFound = TRUE;
                                    ELSEIF c_field_3 REGEXP emailRegExp THEN
                                        SET email = c_field_3;
                                        SET emailFound = TRUE;
                                    ELSEIF c_field_4 REGEXP emailRegExp THEN
                                        SET email = c_field_4;
                                        SET emailFound = TRUE;
                                    ELSEIF c_field_5 REGEXP emailRegExp THEN
                                        SET email = c_field_5;
                                        SET emailFound = TRUE;
                                    ELSEIF c_field_6 REGEXP emailRegExp THEN
                                        SET email = c_field_6;
                                        SET emailFound = TRUE;
                                    ELSEIF c_field_7 REGEXP emailRegExp THEN
                                        SET email = c_field_7;
                                        SET emailFound = TRUE;
                                    ELSEIF c_field_8 REGEXP emailRegExp THEN
                                        SET email = c_field_8;
                                        SET emailFound = TRUE;
                                    ELSEIF c_field_9 REGEXP emailRegExp THEN
                                        SET email = c_field_9;
                                        SET emailFound = TRUE;
                                    ELSEIF c_field_10 REGEXP emailRegExp THEN
                                        SET email = c_field_10;
                                        SET emailFound = TRUE;
                                    END IF;
    
                                    IF rutFound = TRUE AND emailFound = TRUE THEN
                                        INSERT INTO contribuyentes_erroneos (rut, correo)
                                        VALUES (rut, email);
    
                                        SET rut = '';
                                        SET email = '';
                                        SET rutFound = FALSE;
                                        SET emailFound = FALSE;
    
                                    END IF;
    
                            END LOOP loop_taxprayers;
                        END;
                    CLOSE taxpayersCursor;
                END;
    
                SELECT 'PROCESO FINALIZADO CORRECTAMENTE';
    
            END;
        SQL;
    }

    private function dropSP(){
        return <<<SQL
            DROP PROCEDURE IF EXISTS `getTaxpayersData`;
        SQL;
    }

};
