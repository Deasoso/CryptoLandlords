<?php

namespace DDZ;

class Util
{
    protected static $cards;
    protected static $point2card;
    protected static $card2point;

    public static function getCards() {
        if (self::$cards === null) {
            self::$point2card = [];
            foreach (range(3, 10) as $v) {
                self::$point2card[$v] = $v;
            }
            self::$point2card += [
                "11" => "J",
                "12" => "Q",
                "13" => "K",
                "14" => "A",
                "15" => "2",
                "17" => "X",
                "19" => "Y",
            ];
            self::$card2point = array_flip(self::$point2card);

            foreach (self::$card2point as $point) {
                foreach (range(1, 4) as $color) {
                    self::$cards [] = $point * 100 + $color;
                    if ($point > 15) continue 2;
                }
            }
        }

        shuffle(self::$cards);
        return self::$cards;
    }
}