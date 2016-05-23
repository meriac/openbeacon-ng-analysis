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
    $log->time = strftime(TIME_FORMAT, $log->time);
    echo json_encode($log).PHP_EOL;
}

function tag_check_states($time)
{
    global $state, $list_names;

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

function tag_add_sighting($time, $tag_a, $tag_b, $power)
{
    global $sighting, $sighting_power, $sighting_avg, $list_names;

    /* ignore weak sightings */
    if($power < -85)
        return;

    /* optionally resolve primary tag name */
    if(isset($list_names[$tag_a]))
        $tag_a = $list_names[$tag_a];
    /* ignore certain primary tag classes */
    if(($pos = strpos($tag_a,'-'))==FALSE)
    {
        if(intval($tag_a)>0)
            return;
    }
    else
        if(strtolower(substr($tag_a, 0, $pos)) != 'nurse')
            return;

    /* only measure relations to known tags */
    if(!isset($list_names[$tag_b]))
        return;
    $tag_b = $list_names[$tag_b];

    /* remember time of sighting */
    $sighting[$tag_a][$tag_b] = $time;
    $sighting_power[$tag_a][$tag_b][$time] = $power;

    /* ony display after TAG_MIN_SIGHTINGS */
    if(count($sighting_power[$tag_a][$tag_b])>=TAG_MIN_SIGHTINGS)
    {
        $count = $average = 0;
        foreach($sighting_power[$tag_a][$tag_b] as $time_prev => $tmp)
            if(($time - $time_prev)>TAG_LOW_PASS)
                unset($sighting_power[$tag_a][$tag_b][$time_prev]);
            else
            {
                $average += $tmp;
                $count++;
            }
        if($count>0)
        {
            if(($pos = strpos($tag_b,'-'))==FALSE)
                $class = $tag_b;
            else
                $class = substr($tag_b, 0, $pos);

            $sighting_avg[$tag_a][$class][$tag_b] = intval($average/$count + 0.5);
        }
    }
}

function log_file($time, $src, $dest, $message = FALSE)
{
    if(($pos = strpos($dest,'-'))==FALSE)
        $class = $dest;
    else
        $class = substr($dest, 0, $pos);

    /* only prepend if active */
    if($message)
        $message = ','.$message;

    file_put_contents("Sighting-$src-$class.csv",strftime(TIME_FORMAT,$time).",$dest$message\n",FILE_APPEND);
}

function log_sighting($time, $tagid, $log)
{
    global $state_prox,$state_prox_start;

    foreach($log as $id => $power)
    {
        if(!isset($state_prox[$tagid][$id]))
        {
            $state_prox_start[$tagid][$id] = $time;
            log_file($time, $tagid, $id, 'start');
        }

        $state_prox[$tagid][$id] = $time;
    }
}

/* open log file */
$f = bzopen(FILE_NAME, 'r') or die('Couldn\'t open '.FILE_NAME);
$json_stream = '';
$state = array();
$state_prox = array();
$state_prox_start = array();
$list_prev = array();
$sighting = array();
$sighting_power = array();

/* iterate through compressed log file */
while (!feof($f) && (bzerrno($f)==0) ) {

    if(!($buf = bzread($f, 8192)))
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

            /* maintain proximnity states */
            foreach($state_prox as $tag_id => &$tag_list)
            {
                asort($tag_list);
                foreach($tag_list as $id => $time_prev)
                    if(($time-$time_prev)>TAG_TIMEOUT)
                    {
                        if(isset($state_prox_start[$tag_id][$id]))
                        {
                            $delta = $time-$state_prox_start[$tag_id][$id];
                            log_file($time_prev+TAG_TIMEOUT, $tag_id, $id, 'stop,'.$delta);
                            unset($state_prox[$tag_id][$id]);
                        }
                        if(isset($state_prox_start[$tag_id][$id]))
                            unset($state_prox_start[$tag_id][$id]);
                    }
            }

            foreach($sighting as $tag_a => $tag_list)
            {
                /* find expired tags */
                foreach($tag_list as $tag_b => $time_prev)
                    if(($time-$time_prev)>=TAG_TIMEOUT)
                    {
                        unset($sighting[$tag_a][$tag_b]);
                        unset($sighting_power[$tag_a][$tag_b]);
                        if(isset($sighting_avg[$tag_a][$tag_b]))
                            unset($sighting_avg[$tag_a][$tag_b]);
                    }
            }
 
            /* add new tags */
            foreach($obj->tag as $tag)
            {
                $tag_id = intval($tag->id);

                $log = new stdClass();
                if(isset($list_names[$tag_id]))
                    $log->id = $list_names[$tag_id];
                else
                    $log->id = $tag_id;

                switch($tag_id)
                {
                    /* angle matters only for drug trolley */
                    case 0xE51D72F5:
                        $log->angle = $tag->angle;
                        break;
                }

                /* common log entries */
                $log->time = $time;

                /* ignore unmodified log entry */
                if(!tag_changed_state($log))
                    continue;

                /* log angle */
                if(isset($log->angle))
                    log_file($time, $log->id, 'Angle', $log->angle);

                /* log tag */
                tag_log_entry($log);
            }

            /* process all tag sightings */
            $sighting_avg = array();
            foreach($obj->edge as $edge)
            {
                $tag_a = $edge->tag[0];
                $tag_b = $edge->tag[1];

                tag_add_sighting($time, $tag_a, $tag_b, $edge->power);
                tag_add_sighting($time, $tag_b, $tag_a, $edge->power);
            }
            foreach($sighting_avg as $tag_a => &$classes)
            {
                $log = array();

                foreach($classes as &$tags)
                {
                    arsort($tags);
                    reset($tags);
                    $log[key($tags)] = current($tags);
                }

                asort($log);
                log_sighting($time, $tag_a, $log);
            }
            unset($sighting_avg);
        }
    }
}

/* done */
bzclose($f);
