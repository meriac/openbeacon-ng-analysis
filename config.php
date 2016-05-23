<?php
/***************************************************************
 *
 * OpenBeacon.org - Logfile Analysis Tools
 *
 * Copyright 2015 Milosch Meriac <milosch@meriac.com>
 *
 ***************************************************************

 This file is part of the get.OpenBeacon.org logfile analysis tools

 OpenBeacon is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 OpenBeacon is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with OpenBeacon.  If not, see <http://www.gnu.org/licenses/>.

*/
define('FILE_NAME', 'Logfiles/ward.log.bz2');
define('TAG_LOW_PASS', 5);
define('TAG_TIMEOUT', 10);
define('TAG_MIN_SIGHTINGS', 5);
define('TIMEZONE', 'GMT');
define('TIME_FORMAT', '%d.%m.%y %H:%M:%S');

$list_names = array(
    /* nurses */
    0x34D602F5 => 'Nurse-697219',
    0x7037BA27 => 'Nurse-696387',
    0x3F505D93 => 'Nurse-696782',
    0x672F57C4 => 'Nurse-696281',
    0x099A7EC6 => 'Nurse-697004',
    0x0240E1C0 => 'Nurse-696267',
    0x1D5D0EC4 => 'Nurse-696748',
    0x3467706D => 'Nurse-696978',
    0x64B9FDA1 => 'Nurse-696236',
    0x113C041D => 'Nurse-696412',

    /* curtains */
    0xC4910B59 => 'Curtain-A',
    0x9EA71C54 => 'Curtain-B',
    0xB815DFC4 => 'Curtain-C',
    0x8875EFAE => 'Curtain-D',
    0x87CA91E9 => 'Curtain-E',

    /* beds */
    0x338E3796 => 'Bed-A',
    0x2FC1CDB3 => 'Bed-B',
    0x9EDC2331 => 'Bed-C',
    0xB9A9A9EA => 'Bed-D',
    0x0C68FE14 => 'Bed-E',

    /* equipment */
    0xE51D72F5 => 'DrugTrolley'
);
