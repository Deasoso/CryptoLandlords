<?php


namespace DDZ;

class Room
{
    public $id = -1;
    public $players = [];
    public $speaker; // players id
    public $coverCards;
    public $groupCards; // player cards
    public $state; // 0: waiting, 1: calling, 2: playing
    public $lastMessage; // most recent \DDZ\Message
    public $callCount;
    public $roundid = -1;
    public $isreadytopay = false;
    public $coin = 10;

    public function __construct($id) {
        $this->id = $id;
    }

    public function reset() {
        $this->speaker = mt_rand(0, 2);
        $this->state = 0;

        $cards = Util::getCards();
        $cards = array_chunk($cards, 17);
        $this->coverCards = array_pop($cards);
        $this->groupCards = $cards;
        $this->callCount = 0;
    }

    public function tickSpeaker() {
        $this->speaker = ($this->speaker+1) % 3;
    }

    public function requestpay($roundid) { // could be better
        if (!$this->isreadytopay) {
            return ;
        }
        $this->roundid = $roundid;
        $source = $this->lastMessage->getSource(); // poor
        Console::out("{$roundid} requested");
        foreach ($this->players as $node) {
            if ($node->ispayed){
                return ;
            }else{
                $source = $this->lastMessage->getSource(); // poor
                $source->send(json_encode([
                    "action" => "pay",
                    "roundid" => $this->roundid,
                ]), $node);
            }
        }
    }

    public function payed(){
        if (! $this->isAllPayed()) {
            return ;
        }
        $this->reset();
        $this->state = 1;
        $source = $this->lastMessage->getSource(); // poor
        foreach ($this->players as $node) {
            $cards = array_pop($this->groupCards);
            $node->cards = $cards;
            $source->send(json_encode([
                "action" => "start",
                "speaker" => $this->speaker,
                "playerId" => $node->id,
                "cards" => $cards,
            ]), $node);
        }
    }
    
    public function createround(){
        if (! $this->isAllReady()) {
            return ;
        }
        $source = $this->lastMessage->getSource(); // poor
        $playeraddr=array();
        $i = 0;
        foreach ($this->players as $node) {
            $playeraddr[$i] = $node->address;
            $i++;
        }
        foreach ($this->players as $node) {
            if ($node->iscreator){
                $source->send(json_encode([
                    "action" => "createnewround",
                    "player1" => $playeraddr[0],
                    "player2" => $playeraddr[1],
                    "player3" => $playeraddr[2],
                    "coin" => $this->coin,
                ]), $node); // poor
            }else{
                $source->send(json_encode([
                    "action" => "waitingnewround",
                ]), $node); // poor
            }
        }
    }

    public function play($caller) {
        $this->state = 2;
        $this->prepareMaster($caller);
        $this->broadcast([
            "action" => "play",
            "speaker" => $caller->id,
            "roomState" => $this->state,
            "data" => [
                "coverCards" => $this->coverCards
            ]
        ]);
    }
    
    public function sendamessage(){
        foreach ($this->players as $nodes) {
            Console::out("messages:");
            Console::out($nodes);
            Console::out($nodes->getConnection());
            $nodes->getConnection()->send(json_encode([
                "action" => "message",
                // "data" => ["players" => $room->players]
            ]));
        }
    }
    public function gameOver($masterWin) {
        $cards = [];
        $masteraddr = "";
        foreach ($this->players as $node) {
            $cards[$node->id] = (array) $node->getCards();
            if ($node->master){
                $masteraddr = $node->address;
            }
        }
        $this->broadcast([
            "action" => "gameOver",
            "data" => [
                "masterWin" => $masterWin,
                "cards" => $cards,
                "masteraddr" => $masteraddr,
                "roundid" => $this->roundid,
            ],
        ]);
    }

    public function prepareMaster($node) {
        $node->setMaster(true);
        $cards = $node->getCards();
        $node->cards = array_merge($cards, $this->coverCards);
    }

    public function broadcast($data, $source = null) {
        Console::out("broadcasting");
        $source = $source ?: $this->lastMessage->getSource(); // poor
        $message = json_encode($data);
        foreach ($this->players as $node) {
            $source->send($message, $node);
        }
    }

    public function broadcastLastMessage($appendData = []) {
        $message = $this->lastMessage->toArray();
        $message["data"] += $appendData;
        $message["speaker"] = $this->speaker;
        $this->broadcast($message);
    }

    public function isAllReady() {
        $count = 0;
        foreach ($this->players as $node) {
            if ($node->ready)
                $count++;
        }
        return $count === 3;
    }
    
    public function isAllPayed() {
        $count = 0;
        foreach ($this->players as $node) {
            if ($node->ispayed)
                $count++;
        }
        return $count === 3;
    }

    public function getPlayers() {
        $players = [];
        foreach ($this->players as $id => $player) {
            $players[$id] = [
                "ready" => $player->ready,
            ];
        }
        return $players;
    }

    public function addPlayer(Player $node, $seatIndex) {
        if ($node->roomId !== -1) {
            return [false, "already in room #{$node->roomId}"];
        }

        if (count($this->players) === 3) {
            return [false, "enough players"];
        }

        if (isset($this->players[$seatIndex])) {
            return [false, "seat taken"];
        }

        // foreach ($this->players as $id => $player) {
        //     if ($player->address == $node->address) return [false, "already in room"];
        // } //will back

        if (count($this->players) === 0){
            $node->firstinroom();
        }
        $node->joinRoom($this->id, $seatIndex);
        $this->players[$seatIndex] = $node;
        return [true, "joined @{$seatIndex}"];
    }

    public function removePlayer(Player $node) {
        if (isset ($this->players[$node->id])) {
            unset ($this->players[$node->id]);
        }
        $node->id = -1;
        $node->roomId = -1;
    }

    public function resetPlayers() {
        foreach ($this->players as $node) {
            $node->reset();
        }
    }

    public function info() {
        return [
            "id" => $this->id,
            "speaker" => $this->speaker,
            "state" => $this->state,
        ];
    }
}