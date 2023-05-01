<?php
declare(ticks=1);

namespace Program;

use Application\Application;
use IO\Console;

class Main
{
    private int $created, $finished, $success, $fails;

    public function __construct(array $args)
    {
        Console::Write("Input the first URL: ");
        $url1 = Console::ReadLine();
        Console::Write("Input the second URL: ");
        $url2 = Console::ReadLine();
        Console::Write("Requests count (-1 for infinity): ");
        $max_requests = intval(Console::ReadLine());
        $this->HandleUrl("1", $url1, $max_requests);
        Console::WriteLine("\n\n");

        Application::Wait(5000);
        $this->HandleUrl("2", $url2, $max_requests);
        Console::WriteLine("\nPress ENTER to close");
        Console::ReadLine();
    }

    private function Request(string $url) : void
    {
        $request = new AsyncCurl();
        $ch = $request->GetCurlHandle();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_POST, true);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, "...");
        //curl_setopt($ch, CURLOPT_COOKIE, "...");
        curl_setopt($ch, CURLOPT_ENCODING, "");

        $this->created++;
        $request->ExecutedCallback = function(string $html, $ch) : void
        {
            $this->finished++;
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($status == 200)
            {
                $this->success++;
            }
            else
            {
                $this->fails++;
            }
        };
        $request->Execute();
    }

    private function HandleUrl(string $num, string $url, int $max_requests) : void
    {
        Console::WriteLine("\n");
        $this->success = 0;
        $this->fails = 0;
        $this->created = 0;
        $this->finished = 0;
        $time_start = microtime(true);

        Console::WriteLine("URL #" . $num . ": " . $url);
        Console::Write("Created: " . $this->created . ", in process: " . ($this->created - $this->finished) , ", success: " . $this->success . ", fails: " . $this->fails . " (" . $max_requests . ") " . round(microtime(true) - $time_start, 4));

        for ($i = 1; $i <= $max_requests || $max_requests == -1; $i++)
        {
            $this->Request($url);
            Console::ClearLine("Created: " . $this->created . ", in process: " . ($this->created - $this->finished) . ", success: " . $this->success . ", fails: " . $this->fails . " (" . $max_requests . ") " . round(microtime(true) - $time_start, 4));
        }

        do
        {
            Console::ClearLine("Created: " . $this->created . ", in proccess: " . ($this->created - $this->finished) . ", success: " . $this->success . ", fails: " . $this->fails . " (" . $max_requests . ") " . round(microtime(true) - $time_start, 4));
            Application::Wait(1);
        }
        while ($this->finished < $max_requests);
        Console::ClearLine("Created: " . $this->created . ", in process: " . ($this->created - $this->finished) . ", success: " . $this->success . ", fails: " . $this->fails . " (" . $max_requests . ") " . round(microtime(true) - $time_start, 4));
    }
}