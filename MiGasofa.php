<?php

/**
 * $Id: M¡Gasofa.php 47 2012-10-10 18:00:00Z 
 *
 * Copyright (c) 2012, Pablo Velasco Molinero, Pablo Sánchez Lozano.  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 */

/**
 * MiGasofa PHP class
 * @author Pablo Velasco Molinero
 * @author Pablo Sánchez Lozano
 * @version 0.0.1
 */
class MiGasofa {

	// URL desde la que se descargan los ficheros CSV con los precios de la gasolina
	const DOWNLOAD_URL = "http://geoportal.mityc.es/hidrocarburos/files/"; 

	/**
	 * getDistance 
	 *
	 * Calcula la distancia entre dos puntos definidos por su latitud y longitud
	 * usando la fórmula de Haversine
	 *
	 * @param float $lat1 latitud del punto 1
	 * @param float $long1 latitud del punto 1
	 * @param float $lat2 latitud del punto 2
	 * @param float $long2 latitud del punto 2
	 * @param float $distance distancia entre los dos puntos
	 */
	private function getDistance($lat1, $long1, $lat2, $long2)
	{
		$earth = 6371; 	// Radio de la Tierra en km
		
		$lat1  = deg2rad($lat1);
		$long1 = deg2rad($long1);

		$lat2  = deg2rad($lat2);
		$long2 = deg2rad($long2);
		
		// Fórmula de Haversine
		$dlong = $long2-$long1;
		$dlat  = $lat2-$lat1;
		
		$sinlat  = sin($dlat/2);
		$sinlong = sin($dlong/2);
		
		$a = ($sinlat*$sinlat)+cos($lat1)*cos($lat2)*($sinlong*$sinlong);
		
		$c = 2*asin(min(1,sqrt($a)));
		$distance = round($earth * $c, 1);
				
		return $distance;
	}
	
	/**
	 * downloadFile 
	 *
	 * Descarga un fichero procedente de una URL y la guarda en la dirección indicada
	 *
	 * @param string $url URL del fichero a descargar
	 * @param string $path ruta en la que se guarda el fichero descargado
	 */
	private function downloadFile ($url, $path) 
	{
		$newfilename = $path;
		$file = fopen ($url, "rb");
  	
		if ($file) 
		{
			$newfile = fopen ($newfilename, "wb");

			if ($newf)
				while(!feof($file)) 
      				fwrite($newfile, fread($file, 1024 * 8 ), 1024 * 8 );
		}

  		if ($file) 
    		fclose($file);

  		if ($newf) 
    		fclose($newfile);
	}
	
	/**
	 * unzipFile 
	 *
	 * Descomprime un archivo .zip
	 *
	 * @param string $file ruta del fichero a descomprimir
	 * @param string $path ruta en la que se guarda el fichero descomprimido
	 * @return bool devuelve TRUE si va bien, FALSE en caso contrario  
	 */
	private function unzipFile ($file, $path)
	{
		$zip = new ZipArchive;
		$res = $zip->open($file);
     	
		if ($res === TRUE) 
		{
			$zip->extractTo($path);
			$zip->close();

			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * getFileName 
	 *
	 * Calcula el nombre del fichero a descargar de la página del gobierno.
	 * Cada día se crea un nuevo fichero con los precios de la gasolina y se
	 * le nombra en función de la fecha actual
	 *
	 * @param string $gas_type tipo de combustible (G95|G98|GOA|NGO)
	 * @return string nombre del fichero con los datos de hoy
	 */
	private function getFileName($gas_type)
	{
		$date = date('d-m-Y');	
		$date = explode('-', $date);
		
		$day   = $date[0];
		$month = $date[1];
		$year  = $date[2];
		
		$date_file_name = $day . $month . $year; 
		
		$file_name = 'eess_' . $gas_type . '_' . $date_file_name . '.csv'; 
		
		return $file_name;
	}
	
	/**
	 * getGasStations 
	 *
	 * Devuelve en un array los datos de todas las gasolineras encontradas
	 * en unos km a la redonda determinados por un radio, una latitud y una
	 * longitud concretas. 
	 * Cada posición del array asociativo tiene los siguientes campos:
	 * 		- latitude: latitud de la gasolinera
	 * 		- longitude: longitud de la gasolinera
	 * 		- distance: distancia entre la gasolinera y la posición dada por $lat y $long
	 * 		- price: precio de la gasolina para el combustible elegido 
	 *		- description: marca y horarios de la gasolinera
	 *
	 * @param float $lat latitud de la posición actual
	 * @param float $long longitud de la posición actual
	 * @param string $gas_type tipo de combustible (G95|G98|GOA|NGO)
	 * @param float $radius radio de búsqueda en km
	 * @return array nombre del fichero con los datos de hoy
	 */
	public function getGasStations($lat, $long, $gas_type, $radius=5.0)
	{
		$path = '/var/www/vhosts/httpdocs/assets/cheapgas/';
		$gas_stations = NULL;
		$index = 0;
		
		if ($gas_type=='G95' || $gas_type=='G98' || $gas_type=='GOA' || $gas_type=='NGO')
		{
			$file = $this->getFileName($gas_type);

			// Se comprueba si el fichero existe. Si no existe se descargará
			if ( ! file_exists($path . $file))
			{
				$download_file = str_replace('.csv', '.zip', $file);
				$this->downloadFile(self::DOWNLOAD_URL . $download_file, 
									$path . $download_file);
				$this->unzipFile($path . $download_file, $path);	
				unlink($path . $download_file);		
			}
			
			// Se lee el fichero
			if (($reader = fopen($path . $file, "r")) !== FALSE) 
			{
			    while (($data = fgetcsv($reader, 1000, ",")) !== FALSE) 
			    {
					@$distance = $this->getDistance($data[1], $data[0], $lat, $long);
					if ($distance < $radius)
					{
						$price = str_replace(',', '.', substr($data[2], 
											 strlen($data[2])-7, 5));
						$description = substr($data[2], 0, strlen($data[2])-8);
						$gas_stations[$index]['latitude'] = $data[1];
						$gas_stations[$index]['longitude'] = $data[0];
						$gas_stations[$index]['distance'] = $distance;
						$gas_stations[$index]['price'] = $price;
						$gas_stations[$index]['description'] = $description;
						
						$index++;
					}
			    }
				
			    fclose($reader);
			}
			
			return $gas_stations;
		}
	}
}
?>