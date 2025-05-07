<?php

namespace App\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class Helper extends WebhookHandler
{
    /**
     * @param $action
     * @return Keyboard
     */
    public static function getHoursKeyboard($action, $city = ''): Keyboard
    {
        $keyboard = Keyboard::make();
        $row = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = self::addZeroForTime((string)$i);
            if (!empty($city)) {
                $row[] = Button::make($hour)->action('subWeather')->param('hour', $hour)->param('step', 2)->param('city', $city);
            } else {
                $row[] = Button::make($hour)->action($action)->param('hour', $hour)->param('step', 2);

            }
        }
        $chunks = array_chunk($row, 6);
        foreach ($chunks as $chunk) {
            $keyboard->row($chunk);
        }
        return $keyboard;

    }

    /**
     * @param $action
     * @param $hour
     * @return Keyboard
     */
    public static function getMinutesKeyboard($action, $hour, $city = ''): Keyboard
    {
        $keyboard = Keyboard::make();
        $row = [];
        for ($i = 0; $i < 60; $i += 5) {
            $minute = self::addZeroForTime((string)$i);
            if (!empty($city)) {
                $row[] = Button::make($minute)->action($action)->param('hour', $hour)->param('minute', $minute)->param('step', 3)->param('city', $city);
            } else {
                $row[] = Button::make($minute)->action($action)->param('hour', $hour)->param('minute', $minute)->param('step', 3);
            }
        }
        $chunks = array_chunk($row, 6);
        foreach ($chunks as $chunk) {
            $keyboard->row($chunk);
        }
        return $keyboard;

    }


    /**
     * @param $time
     * @return string
     */
    public static function addZeroForTime($time): string
    {
        if (strlen($time) < 2) {
            $time = '0' . $time;
        }
        return $time;
    }
}
