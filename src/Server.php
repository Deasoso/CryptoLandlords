<?php


namespace DDZ;

use Hoa\Event\Bucket;
use Hoa\Websocket\Server as WsServer;

class Server extends WsServer
{
    public $rooms = [];

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
        if ($nodeCount > $roomCount * 3) {
            $this->addRoom();
        }
    }

    public function onClose(Bucket $bucket) {
        $node = $bucket->getSource()->getConnection()->getCurrentNode();

        if (isset($this->rooms[$node->roomId])) {
            $room = $this->rooms[$node->roomId];
            $room->broadcast([
                "action" => "leave",
                "playerId" => $node->id,
            ], $bucket->getSource());
            $room->removePlayer($node);
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
                        ? $room->broadcastLastMessage(["players" => $room->getPlayers()])
                        : $source->send($reason);
                    break;
                case 'listRoom':
                    $source->send(json_encode($message->toArray() + [
                        "rooms" => $this->getAllRooms()
                    ]));
                    break;
                default:
                    $source->send("not in a room");
            }
            return;
        }

        $room->lastMessage = $message;

        switch ($message->action) {
            case "leave":
                $room->broadcastLastMessage([
                    "playerId" => $node->id
                ]);
                $node->reset();
                $room->removePlayer($node);
                break;

            case "ready":
                $node->ready = true;
                $room->broadcastLastMessage(["players" => $room->getPlayers()]);
                $room->start();
                break;

            case "call":
                $room->callCount++;
                if ($message->data["confirmed"]) {
                    $room->play($node);
                } else {
                    if ($room->callCount > 2) {
                        $room->reset();
                        $room->start();
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