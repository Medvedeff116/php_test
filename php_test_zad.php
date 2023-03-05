<?php
/*
Написать класс Profiler, умеющий замерять скорость работы программы.
Важно, чтобы каждый таймер не учитывал время вложенных таймеров.
*/

interface IProfiler
{
    public function startTimer(string $timerName);
    public function endTimer(string $timerName);
    public function getTimers() :array;
}

class ProfilerTimer
{
    protected $_duration = 0;
    protected $_startTime;
    protected $_count;
    protected $_timerName;

    public function __construct($timerName)
    {
        $this->_timerName = $timerName;
    }

    public function start($onlyContinue = false)
    {
        $this->_startTime = microtime(1);

        if (!$onlyContinue) {
            $this->_count++;
            print('Start timer ' . $this->_timerName . PHP_EOL);
        } else {
            print('Continue timer ' . $this->_timerName . PHP_EOL);
        }
    }

    public function stop($onlyPause = false)
    {
        $this->_duration += microtime(1) - $this->_startTime;
        $this->_startTime = null;
        if (!$onlyPause) {
            print('Stoped timer ' . $this->_timerName . ' ('. $this->getDuration() .' sec)' . PHP_EOL);
        } else {
            print('Paused timer ' . $this->_timerName . ' ('. $this->getDuration() .' sec)' . PHP_EOL);
        }
    }

    public function getCount()
    {
        return $this->_count;
    }

    public function getDuration()
    {
        return round($this->_duration, 3);
    }
}


class Profiler implements IProfiler
{
    protected $_timers = [];
    protected $_running = [];


    public function startTimer(string $timerName)
    {
        /** @var ProfilerTimer $timer */
        if (!isset($this->_timers[$timerName])) {
            $this->_timers[$timerName] = new ProfilerTimer($timerName);
        }
        $timer = $this->_timers[$timerName];

        // paused preview timer
        if (count($this->_running)) {
            /** @var ProfilerTimer $prev */
            $prev = end($this->_running);
            $prev->stop(true);
        }
        array_push($this->_running, $timer);
        $timer->start();
    }

    public function endTimer(string $timerName)
    {
        /** @var ProfilerTimer $timer */
        $timer = $this->_timers[$timerName] or die('timer does not exists');
        $timer->stop();

        // remove current timer
        if (count($this->_running)) {
            array_pop($this->_running);
        }

        // continue prev timer
        if (count($this->_running)) {
            /** @var ProfilerTimer $prev */
            $prev = end($this->_running);
            $prev->start(true);
        }
    }

    public function getTimers(): array
    {
        $timers = [];
        array_map(function($timerName, ProfilerTimer $timer) use(&$timers) {
            $timers[$timerName] = ['count' => $timer->getCount(), 'duration' => $timer->getDuration()];
        }, array_keys($this->_timers), array_values($this->_timers));
        return $timers;
    }
}

function testProfiler(IProfiler $profiler) {
    $profiler->startTimer('main');

    sleep(1);

    $profiler->startTimer('doLoop');

    sleep(3);

    for ($i = 0; $i < 10; $i++) {
        $profiler->startTimer('processItem');
        sleep(1);
        $profiler->endTimer('processItem');
    }

    sleep(2);


    $profiler->endTimer('doLoop');

    usleep(200000); //Спим 0.2 секунды

    $profiler->startTimer('doLoop');
    $profiler->endTimer('doLoop');

    $profiler->endTimer('main');

    $result = $profiler->getTimers();

    //Вот это должно вернуть скорее всего. Время округляем до 0.001 секунды.
    $correctResult = [
        'processItem' => [ //10 раз спали по секунде на строке 25
            'count' => 10, //Количество запусков таймера с таким именем
            'duration' => 10, //Суммарная продолжитьность запусков таймера с таким именем без времени вложенных таймеров
        ],
        'doLoop' => [ //3 секунды на строке 21 и ещё 2 секунды на строке 29. То что мы спали на строке 25 не берём, так как этот код внутри вложенного таймера.
            'count' => 2, //Два замера doLoop, на строке 19 и 36. На строке 36 считаем что он работал меньше милесекунды.
            'duration' => 5,
        ],
        'main' => [ //1 секунду спали на строке 17 и ещё 0.2 секунды на строке 34. Всё что вложено в doLoop не берём.
            'count' => 1,
            'duration' => 1.2,
        ],
    ];

    print(PHP_EOL . PHP_EOL . 'Test result: ' . PHP_EOL);
    print_r($result);

    print(PHP_EOL . '========================' . PHP_EOL);

    print('Expected result: ');
    print_r($correctResult);
}


testProfiler(new Profiler());
