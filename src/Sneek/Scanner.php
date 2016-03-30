<?php

namespace Sneek;

use Mandrill;

class Scanner
{
    public  $infected_files = [];
    private $scanned_files  = [];
    /**
     * @var Mandrill
     */
    private $mandrill;

    public function __construct(Mandrill $mandrill)
    {
        $this->mandrill = $mandrill;
    }

    public function run($dir, $to, $subject)
    {
        $this->scan($dir);
        $this->sendalert($to, $subject);
    }

    private function scan($dir)
    {
        $this->scanned_files[] = $dir;
        $files                 = scandir($dir);

        if ( ! is_array($files))
        {
            throw new Exception('Unable to scan directory ' . $dir . '.  Please make sure proper permissions have been set.');
        }

        foreach ($files as $file)
        {
            if (is_file($dir . '/' . $file) && ! in_array($dir . '/' . $file, $this->scanned_files))
            {
                $this->check(file_get_contents($dir . '/' . $file), $dir . '/' . $file);
            }
            elseif (is_dir($dir . '/' . $file) && substr($file, 0, 1) != '.')
            {
                $this->scan($dir . '/' . $file);
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
                'text' => $message,
                'from_email' => 'developers@sneekdigital.co.uk',
                'from_name' => 'Sneek Digital',
                'subject' => $subject,
                'to'   => [
                    ['email' => $to]
                ]
            ]);
        }
    }
}