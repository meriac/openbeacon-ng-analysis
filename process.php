#!/usr/bin/php

<?php

define('FILE_NAME', 'ward.log.bz2');
define('TAG_TIMEOUT', 10);
date_default_timezone_set('GMT');

$list_names = array(
    /* nurses */
    0x34D602F5 => 'Nurse:697219',
    0x7037BA27 => 'Nurse:696387',
    0x3F505D93 => 'Nurse:696782',
    0x672F57C4 => 'Nurse:696281',
    0x099A7EC6 => 'Nurse:697004',
    0x0240E1C0 => 'Nurse:696267',
    0x1D5D0EC4 => 'Nurse:696748',
    0x3467706D => 'Nurse:696978',
    0x64B9FDA1 => 'Nurse:696236',
    0x113C041D => 'Nurse:696412',

    /* curtains */
    0xC4910B59 => 'Curtain:A',
    0x9EA71C54 => 'Curtain:B',
    0xB815DFC4 => 'Curtain:C', 
    0x8875EFAE => 'Curtain:D',
    0x87CA91E9 => 'Curtain:E',

    /* beds */
    0x338E3796 => 'Bed:A',
    0x2FC1CDB3 => 'Bed:B',
    0x9EDC2331 => 'Bed:C',
    0xB9A9A9EA => 'Bed:D',
    0x0C68FE14 => 'Bed:E',

    /* equipment */
    0xE51D72F5 => 'DrugTrolley'
);

$f = bzopen(FILE_NAME, 'r') or die('Couldn\'t open '.FILE_NAME);
$json_stream = '';

$state = array();

function tag_log_entry($tag)
{
    $obj = clone $tag;
    $obj->time = strftime('%d.%m.%y %H:%M:%S', $obj->time);
    echo json_encode($obj).PHP_EOL;
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

bzclose($f);

