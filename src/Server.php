<?php


namespace DDZ;

use Hoa\Event\Bucket;
use Hoa\Websocket\Server as WsServer;

class Server extends WsServer
{
    public $rooms = [];
    public $roomamount = 0;

    public function addRoom() {
        $index = count($this->rooms);
        $this->rooms[$index] = new Room($index);
        $this->rooms[$index]->server = $this;
    }

    /**
     * @return \DDZ\Room
     */
    public function getRoom($roomId) {
        return isset($this->rooms[$roomId]) ? $this->rooms[$roomId] : false;
    }

    public function getAllRooms() {
        $rooms = [];
        foreach ($this->rooms as $k => $room) {
            $rooms[$k] = [
                "id" => $k,
                "players" => array_keys($room->players),
            ];
        }

        return $rooms;
    }

    public function onOpen(Bucket $bucket) {
        $nodeCount = count($bucket->getSource()->getConnection()->getNodes());
        $roomCount = count($this->rooms);
        Console::out("onopen");
        $maxroom = 200;
        while ($this->roomamount < $maxroom) {
            $this -> addRoom();
            $this->roomamount += 1;
        }
    }

    public function onClose(Bucket $bucket) {
        $source = $bucket->getSource();
        $node = $bucket->getSource()->getConnection()->getCurrentNode();
        Console::out("{$node->id} lefting1");
        if (isset($this->rooms[$node->roomId])) {
            Console::out("{$node->getId()} lefting2");
            $room = $this->rooms[$node->roomId];
            $leaveid = $node->id;
            foreach ($room->players as $nodes) {
                // $source->send([
                //             "action" => "goout",
                //             // "data" => ["players" => $room->players]
                //         ]
                //     , $nodes);
                    $nodes->getProtocolImplementation()->send(json_encode([
                        "action" => "goout",
                        // "data" => ["players" => $room->players]
                    ]));
            }
            Console::out("{$node->getId()} lefting3");
            // $room->broadcast([
            //     "action" => "leavee",
            //     "playerId" => $leaveid,
            //     "data" => ["players" => $room->players]
            // ], $bucket->getSource());
            $room->removePlayer($node);
            // $source->send([
            //         "action" => "goout",
            //         // "data" => ["players" => $room->players]
            //     ]);
            Console::out("{$node->getId()} leftroom");
        } else {
            Console::out("{$node->getId()} left");
        }
    }

    public function onMessage(Bucket $bucket) {
        $node = $bucket->getSource()->getConnection()->getCurrentNode();
        $source = $bucket->getSource();
        $message = new Message($bucket);
        Console::out($message->toArray());
        $room = $this->getRoom($message->roomId);

        if (! $room) {
            // list room / join room
            switch ($message->action) {
                case "join":
                    $room = $this->getRoom($message->data["roomId"]);
                    if (! $room) {
                        $source->send("unknown room");
                        return;
                    }
                    $room->lastMessage = $message;
                    list ($result, $reason) = $room->addPlayer($node, ($message->data["playerId"]) % 3);
                    $result
                        ? $room->broadcastLastMessage([ "players" => $room->getPlayers(),
                                                        "roomCoin" => $room->coin])
                        : $source->send($reason);
                    break;
                case 'listRoom':
                    $source->send(json_encode($message->toArray() + [
                        "rooms" => $this->getAllRooms()
                    ]));
                    break;
                case "setaddr":
                    $node->address = $message->data['address'];
                    $node->coin = $message->data['coin'];
                    break;
                case "leave":
                    break;
                default:
                    $source->send("not in a room");

            }
            return;
        }

        $room->lastMessage = $message;

        switch ($message->action) {

            case "leave":
                // $room->sendamessage();
                // break;
                $room->broadcastLastMessage([
                    "playerId" => $node->id,
                    //"players" => $room->getPlayers()
                ]);
                $node->reset();
                $room->removePlayer($node);
                break;

            case "ready":
                $node->ready = true;
                $room->broadcastLastMessage(["players" => $room->getPlayers()]);
                $room->createround();
                break;

            case "call":
                $room->callCount++;
                if ($message->data["confirmed"]) {
                    $room->play($node);
                } else {
                    if ($room->callCount > 2) {
                        $room->reset();
                        $room->payed();
                        Console::out("room restarted.");
                    } else {
                        $room->tickSpeaker();
                        $room->broadcastLastMessage();
                        Console::out("next speaker #{$room->speaker}");
                    }
                }
                break;

            case "shoot":
                $room->tickSpeaker();
                $room->broadcastLastMessage();
                if ($message->data['cards']) {
                    $node->useCards($message->data['cards']);
                    if (empty($node->cards)) {
                        $room->gameOver($masterWin = $node->isMaster());
                        $room->reset();
                        $room->resetPlayers();
                    }
                }
                break;

            case "info":
                print_r($room->info());
                print_r($node->info());
                break;

            case "payed":
                if ($room->id < 100)$node->coin = $node->coin - $room->coin;
                $node->ispayed = true;
                $room->payed();
                break;
                
            case "creatednewround":
                $room->isreadytopay = true;
                $room->requestpay($message->data['roundid']);
                break;

            case "changecoin":
                if ($room->haveready()) return;
                $room->coin = $message->data['newcoin'];
                $room->broadcastLastMessage();
                break;
                
            default:
                $source->send("unknown action.");

        }
    }

    public function start() {
        $this->getConnection()->setNodeName('\DDZ\Player');
        $this->on("open", [$this, 'onOpen']);
        $this->on("close", [$this, 'onClose']);
        $this->on("message", [$this, 'onMessage']);
        $this->run();
    }
}