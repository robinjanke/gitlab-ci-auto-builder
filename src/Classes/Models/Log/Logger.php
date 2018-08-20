<?php

namespace RobinJanke\GitlabCiAutoBuilder\Models\Log;

use RobinJanke\GitlabCiAutoBuilder\Models\Singleton;

class Logger extends Singleton
{

    /**
     * Must be between 0 and 2
     * 0: Only start and end
     * 1: 0 with pipeline stuff
     * 2: 1 with group and project stuff
     * @var int
     */
    protected $logLevel = 0;

    /**
     * @var string
     */
    protected $dateFormat = "Y-m-d H:i:s";

    /**
     * @var string
     */
    protected $logDateTimeToStdout = true;

    /**
     * @param string $message
     * @param int $logLevel
     */
    public function logMessageStdout(string $message, int $logLevel)
    {

        if ($this->logLevel >= $logLevel) {
            $logMessage = "";

            if ($this->logDateTimeToStdout == true) {

                $date = new \DateTime('now');
                $date = $date->format($this->dateFormat);

                /** @var string $date */
                $logMessage .= $date . " ";
            }

            $logMessage .= $message . "\n";

            fwrite(STDOUT, $logMessage);
        }
    }

    /**
     * @return int
     */
    public function getLogLevel(): int
    {
        return $this->logLevel;
    }

    /**
     * @param int $logLevel
     */
    public function setLogLevel(int $logLevel)
    {
        $this->logLevel = $logLevel;
    }

    /**
     * @return string
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat;
    }

    /**
     * @param string $dateFormat
     */
    public function setDateFormat(string $dateFormat)
    {
        $this->dateFormat = $dateFormat;
    }

    /**
     * @return string
     */
    public function getLogDateTimeToStdout(): string
    {
        return $this->logDateTimeToStdout;
    }

    /**
     * @param string $logDateTimeToStdout
     */
    public function setLogDateTimeToStdout(string $logDateTimeToStdout)
    {
        $this->logDateTimeToStdout = $logDateTimeToStdout;
    }

}