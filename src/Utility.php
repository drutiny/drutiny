<?php

namespace Drutiny;

class Utility {
  static public function jsonDecodeDirty($output, $return_array = FALSE)
  {
    $pos = strpos($output, '{');
    if ($pos !== 0) {
      Container::getLogger()->warning("Dirty json output detected. This suggests other errors maybe occuring.");
    }
    $clean = substr($output, $pos);
    return json_decode($clean, $return_array);
  }

  static public function timer()
  {
    return new _UtilityTimer();
  }
}

class _UtilityTimer {
  /**
   * Start time.
   *
   * @var int
   */
  private $start;

  /**
   * End time.
   *
   * @var int
   */
  private $end = FALSE;

  /**
   * Start the timer.
   */
  public function start()
  {
    $this->start = microtime(true);
    return $this;
  }

  /**
   * End the timer.
   *
   * @return int
   *   The number of seconds the timer ran for (rounded).
   */
  public function stop()
  {
    $this->end = microtime(true);
    return bcsub($this->end, $this->start, 2);
  }

  public function getTime()
  {
    if ($this->end === FALSE) {
      throw new \Exception("Cannot get time. Timer is still running.");
    }
    return bcsub($this->end, $this->start, 2);
  }
}
?>
