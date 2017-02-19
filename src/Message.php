<?php


namespace DDZ;

use DDZ\Server;
use Hoa\Event\Bucket;

class Message
{
    public $action = "";
    public $roomId = -1;
    public $playerId = -1;
    public $data = [];
    public $bucket;

    public function __construct(Bucket $bucket) {
        $this->bucket = $bucket;
        $data = $bucket->getData();
        $msg = json_decode($data["message"], true);
        $this->action = $msg["action"];
        $this->roomId = $this->getPlayer()->roomId;
        $this->playerId = $this->getPlayer()->id;

        if (isset($msg["data"])) {
            $this->data = $msg["data"];
        }
    }

    /**
     * @return \DDZ\Player
     */
    public function getPlayer() {
        return $this->bucket->getSource()->getConnection()->getCurrentNode();
    }

    public function getSource() {
        return $this->bucket->getSource();
    }

    public function toArray() {
        return [
            "action" => $this->action,
            "playerId" => $this->getPlayer()->id,
            "roomId" => $this->getPlayer()->roomId,
            "data" => $this->data,
        ];
    }
}