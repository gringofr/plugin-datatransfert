<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

namespace DataTransfert;

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'datatransfert', 'class', 'datatransfert');
require_once dirname(__FILE__) . '/../../vendor/autoload.php';

function timesort($a, $b)
{
    return strcmp($a["time"], $b["time"]);
}

class DataTransfert {
  function setProgressCallback($class) {
    $this->progressCallback = $class;
  }

  function setProgress($id, $progress) {
    $this->progressCallback->setProgress($id, $progress);
  }

  static function guessTimestamp($_name) {
    $formats = array("*-*-*.*.*-Y-m-d-H?i.*.*" => "backup-fensoft-3.1.5-2017-10-04-11h52.tar.gz");
    foreach ($formats as $format => $example) {
      $date = \DateTime::createFromFormat($format, $_name);
      if ($date)
        return $date->format('U');
    }
    return null;
  }

  static function withEqLogic($_eqLogic) {
    \log::add('datatransfert', 'error', "withEqLogic unimplemented");
  }

  function put($_source, $_cible) {
    \log::add('datatransfert', 'error', "put unimplemented");
  }
  
  function ls($_cible) {
    \log::add('datatransfert', 'error', "list unimplemented");
    return array();
  }
  
  function remove($_cible) {
    \log::add('datatransfert', 'error', "remove unimplemented");
  }
  
  function mkdir($_cible) {
    \log::add('datatransfert', 'error', "mkdir unimplemented");
  }
  
  function removeOlder($_cible, $numberToKeep) {
    \log::add('datatransfert', 'info', "removing old files except " . $numberToKeep . " in " . $_cible);
    $ls = $this->ls($_cible);
    $ls2 = array();
    $lsskipped = array();
    foreach ($ls as $val) {
      $guessed = $this->guessTimestamp($val["name"]);
      if ($guessed != null)
        $val["time"] = $guessed;
      if ($val["time"] == null)
        array_push($lsskipped, $val["name"]);
      else
        array_push($ls2, $val);
    }
    if (count($lsskipped) != 0)
      \log::add('datatransfert', 'info', "unknown time, clean skipped for " . implode(',', $lsskipped));
    usort($ls2, "\\DataTransfert\\timesort");
    $todel = array_slice($ls2, 0, -$numberToKeep);
    foreach ($todel as $val) {
      \log::add('datatransfert', 'info', "removing " . $_cible);
      $this->remove($_cible . "/" . $val["name"]);
    }
  }
}

class ProgressWrapper {
  var $fp;
  var $id;
  var $callback;

  static $registered = array();
  static $counter = 0;

  function stream_open($path, $mode, $options, &$opened_path) {
    //\log::add('datatransfert', 'debug', "ProgressWrapper::stream_open " . $path);
    $url = parse_url($path);
    $this->fp = self::$registered[$url['host']]["content"];
    $this->id = self::$registered[$url['host']]["id"];
    $this->callback = self::$registered[$url['host']]["callback"];
    return true;
  }
  function stream_read($count) {
    //\log::add('datatransfert', 'debug', "ProgressWrapper::stream_read " . $count);
    $res = fread($this->fp, $count);
    $this->callback->setProgress($this->id, ftell($this->fp));
    //\log::add('datatransfert', 'debug', "ProgressWrapper::stream_read=" . strlen($res));
    return $res;
  }
  function stream_eof() {
    //\log::add('datatransfert', 'debug', "ProgressWrapper::stream_eof");
    $res = feof($this->fp);
    //\log::add('datatransfert', 'debug', "ProgressWrapper::stream_eof=" . ($res ? "1" : "0"));
    return $res;
  }
  function stream_tell() {
   // \log::add('datatransfert', 'debug', "ProgressWrapper::stream_tell");
    $res = ftell($this->fp);
    //\log::add('datatransfert', 'debug', "ProgressWrapper::stream_tell=" . $res);
    return $res;
  }
  function stream_stat() {
    //\log::add('datatransfert', 'debug', "ProgressWrapper::stream_stat");
    //return self::$stat;
    $res = fstat($this->fp);
    //\log::add('datatransfert', 'debug', "ProgressWrapper::url_stat=" . json_encode($res));
    return $res;
  }
  function stream_seek($offset , $whence) {
    //\log::add('datatransfert', 'debug', "ProgressWrapper::stream_seek " . $offset . " " . $whence);
    $res = fseek($this->fp, $offset, $whence);
    //\log::add('datatransfert', 'debug', "ProgressWrapper::stream_seek=" . $res);
    return 0 === $res;
  }
  static function url_stat($path, $flags) {
    //\log::add('datatransfert', 'debug', "ProgressWrapper::url_stat " . $path);
    //return self::$stat;
    $url = parse_url($path);
    $fp = self::$registered[$url['host']]["content"];
    $res = fstat($fp);
    //\log::add('datatransfert', 'debug', "ProgressWrapper::url_stat=" . json_encode($res));
    return $res;
  }
  static function wrap($what, $id, $callback) {
    if (!in_array("datatransfert", stream_get_wrappers()))
      stream_wrapper_register("datatransfert", __class__);
    self::$counter = self::$counter + 1;
    self::$registered[self::$counter] = array("content" => $what, "id" => $id, "callback" => $callback);
    return fopen("datatransfert://" . self::$counter, "r+");
  }
  
  static function pipe_streams($in, $out)
  {
    $size = 0;
    while (!feof($in)) $size += fwrite($out,fread($in,8192));
    return $size;
  }
}

class Fly extends DataTransfert {
  function basename($_file) {
    if ($this->forceBase)
      return basename($_file);
    return $_file;
  }
  
  function dirname($_file) {
    if ($this->forceBase)
      return trim(dirname($_file), "/");
    return "";
  }

  function put($_source, $_cible) {
    if (isset($this->removeDupes) && $this->removeDupes == true) {
      foreach ($this->ls(dirname($_cible)) as $val) {
        //\log::add('datatransfert', 'debug', "dupes " . $val["alias"] . "==" . basename($_cible));
        if ($val["alias"] == basename($_cible)) {
          $this->remove(dirname($_cible) . "/" . $val["name"]);
        }
      }
    }
    $filesystem = $this->getFly($this->dirname($_cible));
    if ($this->preciseProgress)
      $fp = ProgressWrapper::wrap(fopen($_source, 'r'), $_source, $this);
    else
      $fp = fopen($_source, 'r');
    $filesystem->putStream($this->basename($_cible), $fp);
  }
  
  function timestamp($_val) {
    return $_val["timestamp"];
  }
  
  function ls($_source) {
    $filesystem = $this->getFly($this->dirname($_source));
    $res = array();
    foreach ($filesystem->listContents($this->basename($_source), false) as $val) {
      if ($val["type"] == "file") {
        \log::add('datatransfert', 'debug', "list " . json_encode($val));
        array_push($res, array("name" => $val["basename"], "alias" => $val["filename"] . ($val["extension"]==""?"":".".$val["extension"]), "time" => $this->timestamp($val)));
      }
    }
    \log::add('datatransfert', 'debug', "list " . json_encode($res));
    return $res;
  }
  
  function remove($_cible) {
    \log::add('datatransfert', 'debug', "remove " . $_cible);
    $filesystem = $this->getFly($this->dirname($_cible));
    $filesystem->delete($this->basename($_cible));
  }
  
  function mkdir($_cible) {
    \log::add('datatransfert', 'debug', "mkdir " . $_cible);
    $filesystem = $this->getFly("");
    $filesystem->createDir($_cible);
  }
}
?>
