<?php

namespace Sneek;

use Mandrill;

class Scanner
{
    public  $infected_files = [];
    private $scanned_files  = [];
    private $scan_limit;

    /**
     * @var Mandrill
     */
    private $mandrill;

    public function __construct(Mandrill $mandrill)
    {
        $this->mandrill   = $mandrill;
        $this->scan_limit = pow(10, 6);
    }

    public function run($dir, $to, $subject)
    {
        $this->scan($dir);
        $this->sendalert($to, $subject);
        $this->displayResults();
    }

    private function scan($dir)
    {
        $files = scandir($dir);

        if ( ! is_array($files))
        {
            throw new Exception('Unable to scan directory ' . $dir . '.  Please make sure proper permissions have been set.');
        }

        foreach ($files as $file)
        {
            if (count($this->scanned_files) > $this->scan_limit)
            {
                $this->exitEarly();
            }

            $itemToCheck = $dir . '/' . $file;

            if (is_file($itemToCheck) and ! $this->isChecked($itemToCheck) and $this->isPhpFile($itemToCheck))
            {
                echo sprintf('[CHECKING] %s', $itemToCheck) . PHP_EOL;

                $this->check(file_get_contents($itemToCheck), $itemToCheck);
            }
            elseif (is_dir($itemToCheck) and ! $this->isHiddenDirectory($file) and ! $this->isNodeModulesDirectory($file))
            {
                echo sprintf('[MOVING] %s', $itemToCheck) . PHP_EOL;

                $this->scan($itemToCheck);
            }
        }
    }

    private function check($contents, $file)
    {
        $this->scanned_files[] = $file;
        if (preg_match('/eval\((base64|eval|\$_|\$\$|\$[A-Za-z_0-9\{]*(\(|\{|\[))/i', $contents))
        {
            $this->infected_files[] = $file;
        }
    }

    private function sendalert($to, $subject)
    {
        if (count($this->infected_files) != 0)
        {
            $message = "== MALICIOUS CODE FOUND == \n\n";
            $message .= "The following files appear to be infected: \n";
            foreach ($this->infected_files as $inf)
            {
                $message .= "  -  $inf \n";
            }

            $this->mandrill->messages->send([
                'text'       => $message,
                'from_email' => 'developers@sneekdigital.co.uk',
                'from_name'  => 'Sneek Digital',
                'subject'    => $subject,
                'to'         => [
                    ['email' => $to]
                ]
            ]);
        }
    }

    /**
     * @param $file
     *
     * @return bool
     */
    private function isHiddenDirectory($file)
    {
        return substr($file, 0, 1) == '.';
    }

    private function isNodeModulesDirectory($file)
    {
        return substr($file, mb_strlen('node_modules') * -1) === 'node_modules';
    }

    /**
     * @param $itemToCheck
     *
     * @return bool
     */
    private function isChecked($itemToCheck)
    {
        return in_array($itemToCheck, $this->scanned_files);
    }

    private function isPhpFile($itemToCheck)
    {
        return substr($itemToCheck, - 3) === 'php';
    }

    private function exitEarly()
    {
        $this->displayResults();
        exit;
    }

    private function getMemoryUsage()
    {
        $mem   = memory_get_peak_usage(true);
        $unit  = 0;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'ZB'];

        while ($mem >= 1024)
        {
            $mem /= 1024;
            $unit ++;
        }

        return $mem . $units[$unit];
    }

    private function displayResults()
    {
        $memoryUsage = $this->getMemoryUsage();

        echo '== Usage ==' . PHP_EOL;
        echo 'Memory Usage: ' . $memoryUsage . PHP_EOL;
        echo 'Scanned Files: ' . count($this->scanned_files) . PHP_EOL;
        echo 'Infected Files: ' . count($this->infected_files) . PHP_EOL;
        echo '================' . PHP_EOL;
    }
}