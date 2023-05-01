<?php
declare(ticks=1);

namespace Program;

use \Closure;
use Scheduler\AsyncTask;
use \Exception;

class AsyncCurl
{
    public ?array $Post = null;
    private bool $Executed = false;

    /**
     * @var Closure|null string $body, int $status
     */
    public ?Closure $ExecutedCallback = null;
    private ?AsyncTask $task = null;
    private AsyncCurlParams $task_params;

    public function __construct(?string $url = null)
    {
        $this->task_params = new AsyncCurlParams();

        if ($url === null)
        {
            $this->task_params->ch = curl_init();
        }
        else
        {
            $this->task_params->ch = curl_init($url);
        }
    }

    public function IsExecuting() : bool
    {
        if ($this->task === null || $this->task->IsFinished())
        {
            return false;
        }
        return true;
    }

    /**
     * @return resource
     */
    public function GetCurlHandle()
    {
        return $this->task_params->ch;
    }

    public function Execute() : void
    {
        if ($this->Executed)
        {
            throw new Exception("Request was executed or already executing");
        }
        $this->Executed = true;
        $this->task_params->mh = curl_multi_init();
        curl_multi_add_handle($this->task_params->mh, $this->task_params->ch);

        // Далее переводим выполнение нашего запроса в асинхронную задачу и разбиваем на стадииz
        $this->task_params->_current_stage = 1;
        $this->task_params->active = null;
        $this->task = new AsyncTask($this, 1, false, function(AsyncTask $task, AsyncCurlParams $task_params) : void
        {
            // Делим асинхронную задачу на стадии
            // Стадия 1
            if ($task_params->_current_stage == 1)
            {
                $task_params->mrc = curl_multi_exec($task_params->mh, $task_params->active);
                if (!($task_params->mrc == CURLM_CALL_MULTI_PERFORM))
                    $task_params->_current_stage++;
            }
            // Стадия 2
            else if ($task_params->_current_stage == 2)
            {
                if ($task_params->active && $task_params->mrc == CURLM_OK)
                {
                    if (curl_multi_select($task_params->mh, 0) != -1)
                    {
                        do
                        {
                            $this->mrc = curl_multi_exec($task_params->mh, $task_params->active);
                        }
                        while ($this->mrc == CURLM_CALL_MULTI_PERFORM);
                    }
                }
                else
                    $task_params->_current_stage++;
            }
            // Завершающая стадия
            else
            {
                curl_multi_remove_handle($task_params->mh, $task_params->ch);
                curl_multi_close($task_params->mh);

                $html = curl_multi_getcontent($task_params->ch);

                call_user_func($this->ExecutedCallback, $html, $task_params->ch); // Запускаем наш callback

                $task->Cancel(); // завершаем задачу
            }
        }, $this->task_params);
    }
}