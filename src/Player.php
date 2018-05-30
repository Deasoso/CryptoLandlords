<?php

namespace DDZ;

use Hoa\Websocket\Node;

class Player extends Node
{
    public $id = -1; // room players index / seat id
    public $roomId = -1;
    public $cards = [];
    public $master = false;
    public $ready = false;
    public $ispayed = false;
    public $address = "0";
    public $iscreator = false;

    public function joinRoom($id, $playerId) {
        $this->roomId = $id;
        $this->id = $playerId;
    }

    public function createround(){
        $this->ispayed = true;
    }

    public function firstinroom(){
        $this->iscreator = true;
    }

    public function useCards(array $cards) {
        if (!empty($cards)
            && count($cards) == count(array_intersect($this->cards, $cards))
        ) {
            $this->cards = array_diff($this->cards, $cards);
        }
    }

    public function getCards() {
        return array_values($this->cards);
    }

    public function setMaster($bool = true) {
        $this->master = $bool;
    }

    public function isMaster() {
        return (bool)$this->master;
    }

    public function reset() {
        $this->cards = [];
        $this->master = false;
        $this->ready = false;
    }

    public function info() {
        return [
            "id" => $this->id,
            "cards" => $this->cards,
            "master" => $this->master,
        ];
    }
}