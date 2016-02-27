#!/usr/bin/php
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
require_once('config.php');
date_default_timezone_set(TIMEZONE);

function tag_log_entry($tag)
{
    $log = clone $tag;
    $log->time = strftime('%d.%m.%y %H:%M:%S', $log->time);
    echo json_encode($log).PHP_EOL;
}

function tag_check_states($time)
{
    global $state;

    foreach($state as $id => $prev_state)
    {
        if(($time - $prev_state->time) >= TAG_TIMEOUT)
        {
            /* log tag disappearance */
            $log = new stdClass();
            $log->id = intval($prev_state->id);
            $log->time = $prev_state->time+TAG_TIMEOUT;
            if(isset($prev_state->name))
                $log->name = $prev_state->name;
            $log->action = 'disappear';
            tag_log_entry($log);

            /* remove from state cahce */
            unset($state[$id]);
        }
    }
}

function tag_changed_state($tag)
{
    global $state;

    /* verify object */
    if(!$tag || !is_object($tag))
        return false;

    /* tag exists? */
    if(isset($state[$tag->id]))
    {
        /* get previous tag */
        $tag_prev = $state[$tag->id];
        /* update time */
        $tag_prev->time = $tag->time;
        /* tag state unmodified */
        if($tag_prev==$tag)
            return false;

        /* no changes */
        $change = FALSE;

        /* only store angle changes of 10 degree or larger */
        if(isset($tag->angle))
            $change = $change || (abs($tag_prev->angle - $tag->angle) >= 10);

        /* ignore if no changes */
        if(!$change)
            return false;
    }

    /* check for appearance */
    if(!isset($state[$tag->id]))
        $tag->action = 'appear';

    /* store modified object */
    $state[$tag->id] = $tag;
    return true;
}

/* open log file */
$f = bzopen(FILE_NAME, 'r') or die('Couldn\'t open '.FILE_NAME);
$json_stream = '';
$state = array();

/* iterate through compressed log file */
while (!feof($f) && bzerror($f) ) {

    if(($buf = bzread($f, 8192)) === FALSE)
        break;
    $json_stream .= $buf;

    /* find all complete JSON objects */
    while(($pos = strpos($json_stream,'},{'))!==FALSE)
    {
        /* extract first JSON object */
        $json = substr($json_stream, 0, $pos+1);
        /* trim first JSON object */
        $json_stream = substr($json_stream, $pos+2);
        /* decode JSON object */
        if(($obj = json_decode($json))!==NULL)
        {
            /* scan state cache for vanished tags */
            $time = intval($obj->time);
            tag_check_states($time);

            /* add new tags */
            foreach($obj->tag as $tag)
            {
                $tag_id = intval($tag->id);

                $log = new stdClass();
                if(isset($list_names[$tag_id]))
                    $log->name = $list_names[$tag_id];

                switch($tag_id)
                {
                    /* angle matters only for drug trolley */
                    case 0xE51D72F5:
                        $log->angle = $tag->angle;
                        break;
                }

                /* common log entries */
                $log->id = $tag_id;
                $log->time = $time;

                /* ignore unmodified log entry */
                if(!tag_changed_state($log))
                    continue;

                /* log tag */
                tag_log_entry($log);
            }
        }
    }
}

/* done */
bzclose($f);
