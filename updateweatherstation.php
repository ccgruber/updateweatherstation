<?php

# PHP interface for IP weatherstations using the PWS protocol
# by Christian C. Gruber <cg@chilia.com> 2017

# Protocol implemented from http://wiki.wunderground.com/index.php/PWS_-_Upload_Protocol

# Copyright (C) Christian C. Gruber
#
# The following terms apply to all files associated
# with the software unless explicitly disclaimed in individual files.
#
# The authors hereby grant permission to use, copy, modify, distribute,
# and license this software and its documentation for any purpose, provided
# that existing copyright notices are retained in all copies and that this
# notice is included verbatim in any distributions. No written agreement,
# license, or royalty fee is required for any of the authorized uses.
# Modifications to this software may be copyrighted by their authors
# and need not follow the licensing terms described here, provided that
# the new terms are clearly indicated on the first page of each file where
# they apply.
#
# IN NO EVENT SHALL THE AUTHORS OR DISTRIBUTORS BE LIABLE TO ANY PARTY
# FOR DIRECT, INDIRECT, SPECIAL, INCIDENTAL, OR CONSEQUENTIAL DAMAGES
# ARISING OUT OF THE USE OF THIS SOFTWARE, ITS DOCUMENTATION, OR ANY
# DERIVATIVES THEREOF, EVEN IF THE AUTHORS HAVE BEEN ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.
#
# THE AUTHORS AND DISTRIBUTORS SPECIFICALLY DISCLAIM ANY WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE, AND NON-INFRINGEMENT.  THIS SOFTWARE
# IS PROVIDED ON AN "AS IS" BASIS, AND THE AUTHORS AND DISTRIBUTORS HAVE
# NO OBLIGATION TO PROVIDE MAINTENANCE, SUPPORT, UPDATES, ENHANCEMENTS, OR
# MODIFICATIONS.

# *** SETTINGS START ***

# Debug
error_reporting(E_ALL);
ini_set('display_errors', 'on');

# Settings: General
$device = "auto";
$json_data_log = 1;
$fhem_data_log = 1;
$convert_data = 1;
$forward_data = 1;

# Settings: FHEM
$FHEM_server = "127.0.0.1";
$FHEM_port = "7072";

# Settings: json data log dir
$json_data_logdir = "/var/data";

# Settings: Forward to server
$forward_server = "prod-rapidfire-elb-1119c2afhu1e4-2098141578.us-west-2.elb.amazonaws.com";

# *** SETTINGS END ***

# Convert HTTP GET variables to json
$weather_data = $_GET;

# Forward data to public server
if ( $forward_data == 1 ) 
{
    @$weather_data['forward_url'] = "http://" . $forward_server . $_SERVER[REQUEST_URI];
    @$weather_data['forward'] = file_get_contents($weather_data['forward_url']);
}

# Get weather station identifier if requested
if ( $device == "auto" ) {
    $device = "weather_" . $weather_data['ID'];
}

# Conversion factors
$f_mph_kmh = 1.60934;
$f_mph_kts = 0.868976;
$f_mph_ms = 0.44704;
$f_in_hpa = 33.86;
$f_in_mm = 25.4;

# Convert data
if ( $convert_data == 1 ) 
{
    # Temps
    @$weather_data['windchillc'] = round( ( $weather_data['windchillf'] - 32 ) * 5 / 9, 2 );
    @$weather_data['indoortempc'] = round( ( $weather_data['indoortempf'] - 32 ) * 5 / 9, 2 );
    @$weather_data['tempc'] = round( ( $weather_data['tempf'] - 32 ) * 5 / 9, 2 );
    @$weather_data['dewptc'] = round( ( $weather_data['dewptf'] - 32 ) * 5 / 9, 2 );
    
    # Speeds
    @$weather_data['windgustkmh'] = round( $weather_data['windgustmph'] * $f_mph_kmh, 2 );
    @$weather_data['windspeedkmh'] = round( $weather_data['windspeedmph'] * $f_mph_kmh, 2 );
    @$weather_data['windgustkts'] = round( $weather_data['windgustmph'] * $f_mph_kts, 2 );
    @$weather_data['windspeedkts'] = round( $weather_data['windspeedmph'] * $f_mph_kts, 2 );
    
    # Distances
    @$weather_data['rainmm'] = round( $weather_data['rainin'] * $f_in_mm, 2 );
    @$weather_data['dailyrainmm'] = round( $weather_data['dailyrainin'] * $f_in_mm, 2 );
    @$weather_data['weeklyrainmm'] = round( $weather_data['weeklyrainin'] * $f_in_mm, 2 );
    @$weather_data['monthlyrainmm'] = round( $weather_data['monthlyrainin'] * $f_in_mm, 2 );
    @$weather_data['yearlyrainmm'] = round( $weather_data['yearlyrainin'] * $f_in_mm, 2 );
    
    # Baros
    @$weather_data['baromhpa'] = round( $weather_data['baromin'] * $f_in_hpa, 2 );
    @$weather_data['absbaromhpa'] = round( $weather_data['absbaromin'] * $f_in_hpa, 2 );
    
    # Date and time
    if ( $weather_data['dateutc'] == "now" ) {
        $weather_data['dateutc'] = gmdate("Y-m-d\TH:i:s\Z");
    }
}

# Pack data into json format
$weather_data_json = json_encode($weather_data);

# Write json stream to logfile
$json_data_logfile = $json_data_logdir . "/" . $device . ".json";
if ( $json_data_log == 1 ) 
{
    $file = fopen($json_data_logfile, 'a');
    fwrite($file, $weather_data_json);
    fclose($file);
}

# Add settings, json and url string to array
$weather_data['json'] = $weather_data_json;
$weather_data['url'] = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$weather_data['settings_device'] = $device;
$weather_data['settings_convert_data'] = $convert_data;
$weather_data['settings_json_data_log'] = $json_data_log;
$weather_data['settings_json_data_logdir'] = $json_data_logdir;
$weather_data['settings_json_data_logfile'] = $json_data_logfile;
$weather_data['settings_fhem_data_log'] = $fhem_data_log;
$weather_data['settings_forward_data'] = $forward_data;
$weather_data['settings_forward_server'] = $forward_server;
$weather_data['settings_FHEM_server'] = $FHEM_server;
$weather_data['settings_FHEM_port'] = $FHEM_port;

# Write data to FHEM
if ( $fhem_data_log == 1 ) 
{
    $FHEM_device = $device;
    
    $conn=fsockopen($FHEM_server,$FHEM_port);
    if($conn){
    
        # Create FHEM device if not exists
        $FHEM_command = fputs($conn,"define $FHEM_device dummy".chr(10).chr(13));
    
        # Write each value into seperate reading
        foreach ($weather_data as $reading => $value) {
            $FHEM_command = fputs($conn,"setreading $FHEM_device $reading $value".chr(10).chr(13));
        }
    
        # Exit from FHEM interface
        $FHEM_command = fputs($conn,"exit".chr(10).chr(13));
        fclose($conn);
    }
}

print("success");

?>