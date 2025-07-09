<?php
namespace RpiNewsletter\traits;

trait RpiLogging
{
    private bool $debugmode = true;


    public function setDebugMode($debugmode){
        $this->debugmode = $debugmode;
    }

    /**
     * @return mixed
     */
    public function getDebugmode()
    {
        return $this->debugmode;
    }
    protected function log_message($message, $level = 'WARNING')
    {
        if ($this->debugmode) {
            $timestamp = microtime(true);
            $logLevelMap = [
                'INFO' => E_USER_NOTICE,
                'WARNING' => E_USER_WARNING,
                'ERROR' => E_USER_ERROR,
            ];
            $level = strtoupper($level);

            if (!isset($logLevelMap[$level])) {
                throw new \InvalidArgumentException('Invalid log level');
            }

            $formattedMessage = "[$timestamp] [$level] $message" ." ". PHP_EOL. " ";
            error_log($formattedMessage, 0);
            trigger_error($formattedMessage, $logLevelMap[$level]);
        }
    }

}