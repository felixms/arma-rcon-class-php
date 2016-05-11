<?php

namespace Nizarii\ArmaRConClass\Core;


/**
 * Class Operator
 *
 * @internal
 */
class Operator extends Sender {


    
    /**
     * Operator constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection) {
        parent::__construct($connection);
    }
    
    
    /**
     * Sends a custom command to the BattlEye server
     *
     * @param string $command   Command sent to the server
     * @return string           Answer from the server
     */
    public function command($command) {
        return $this->send($command) ? $this->getAnswer() : false;
    }

    
    /**
     * Kicks a player who is currently on the server
     *
     * @param integer $player   The player who should be kicked
     * @return bool             Whether sending the command was successful or not
     */
    public function kickPlayer($player, $reason = 'Admin Kick') {
        return $this->send("kick ".$player." ".$reason) ? $this : false;
    }


    /**
     * Sends a global message to all players
     *
     * @param string $message   The message to send
     * @return bool|$this             Whether sending the command was successful or not
     */
    public function sayGlobal($message) {
        return $this->send("Say -1 ".$message) ? $this : false;
    }


    /**
     * Sends a message to a specific player
     *
     * @param integer $player   Player who is sent the message
     * @param string $message   The message for the player
     * @return bool|$this       Whether sending the command was successful or not
     */
    public function sayPlayer($player, $message) {
        return $this->send("Say ".$player.$message) ? $this : false;
    }


    /**
     * Loads the "scripts.txt" file without the need to restart the server
     *
     * @return bool|$this       Whether sending the command was successful or not
     */
    public function loadScripts() {
        return $this->send("loadScripts") ? $this : false;
    }


    /**
     * Changes the MaxPing value. If a player has a higher ping, he will be kicked from the server
     *
     * @param integer $ping     Max ping
     * @return bool|$this       Whether sending the command was successful or not
     */
    public function maxPing($ping) {
        return $this->send("MaxPing ".$ping) ? $this : false;
    }


    /**
     * Changes the RCon password
     *
     * @param string $password  The new password
     * @return bool|$this       Whether sending the command was successful or not
     */
    public function changePassword($password) {
        return $this->send("RConPassword $password") ? $this : false;
    }


    /**
     * (Re)load the BE ban list from bans.txt
     *
     * @return bool|$this       Whether sending the command was successful or not
     */
    public function loadBans() {
        return $this->send("loadBans") ? $this : false;
    }


    /**
     * Ban a player's BE GUID from the server. If time is not specified or 0, the ban will be permanent;.
     * If reason is not specified the player will be kicked with the message "Banned".
     *
     * @param string $player    Player who will be banned
     * @param string $reason    Reason why the player is banned
     * @param integer $time     How long the player is banned (0 = permanent)
     * @return bool|$this       Whether sending the command was successful or not
     */
    public function banPlayer($player, $reason = "Banned", $time = 0) {
        return $this->send("ban $player $time $reason") ? $this : false;
    }


    /**
     * Same as "ban_player", but allows to ban a player that is not currently on the server
     *
     * @param string $player    Player who will be banned
     * @param string $reason    Reason why the player is banned
     * @param integer $time     How long the player is banned (0 = permanent)
     * @return bool|$this       Whether sending the command was successful or not
     */
    public function addBan($player, $reason = "Banned", $time = 0) {
        return $this->send("addBan $player $time $reason") ? $this : false;
    }


    /**
     * Removes a ban
     *
     * @param integer $banid    Ban who will be removed
     * @return bool|$this       Whether sending the command was successful or not
     */
    public function removeBan($banid) {
        return $this->send("removeBan ".$banid) ? $this : false;
    }


    /**
     * Removes expired bans from bans file
     *
     * @return bool|$this       Whether sending the command was successful or not
     */
    public function writeBans() {
        return $this->send("writeBans") ? $this : false;
    }


    /**
     * Gets a list of all players currently on the server
     *
     * @return string|bool      The list of all players on the server or, if sending failed, false
     */
    public function getPlayers() {
        return $this->send("players") ? $this->getAnswer() : false;
    }


    /**
     * Gets a list of all players currently on the server as an array
     *
     * Big thanks to nerdalertdk for providing this nice function
     *
     * @author nerdalertdk (https://github.com/nerdalertdk)
     * @link https://github.com/Nizarii/arma-rcon-class-php/issues/4
     * @return array|bool      The list of all players on the server or, if sending failed, false
     */
    public function getPlayersArray() {
        $playersRaw = $this->send("players");

        if ( $playersRaw === false )
            return false;

        $players = $this->cleanList($playersRaw);
        preg_match_all("#(\d+)\s+(\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+\b)\s+(\d+)\s+([0-9a-fA-F]+)\(\w+\)\s([\S ]+)$#im", $players, $str);
        $result = $this->formatList($str);

        return $result;
    }
    

    /**
     * Gets a list of all bans as an array
     * 
     * @return string|bool      The list of bans or, if sending failed, false
     */
    public function getBans() {
        return $this->send("bans") ? $this->getAnswer() : false;
    }


    /**
     * Gets a list of all bans
     *
     * Big thanks to nerdalertdk for providing this nice function
     *
     * @author nerdalertdk (https://github.com/nerdalertdk)
     * @link https://github.com/Nizarii/arma-rcon-class-php/issues/4
     * @return array|bool      The list of bans or, if sending failed, false
     */
    public function getBansArray() {
        $bansRaw = $this->send("bans");

        if ( $bansRaw === false )
            return false;

        $bans = $this->cleanList($bansRaw);
        preg_match_all("#(\d+)\s+(\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+\b)\s+(\d+)\s+([0-9a-fA-F]+)\(\w+\)\s([\S ]+)$#im", $bans, $str);
        $result = $this->formatList($str);
        
        return $result;
    }


    /**
     * Gets a list of all bans
     *
     * @return string|bool      The list of missions or, if sending failed, false
     */
    public function getMissions() {
        return $this->send("missions") ? $this->getAnswer() : false;
    }
    
     /**
     * Converts BE text "array" list to Array
     *
     * @author nerdalertdk (https://github.com/nerdalertdk)
     * @link https://github.com/Nizarii/arma-rcon-class-php/issues/4
     * @return array
     */
    private function formatList($str)
	{
		// Remove first array
		array_shift($str);
		// Create return array
		$result = array();
		// Loop true the main arrays, each holding a value
		foreach($str as $key => $value)
		{
			// Combine's each main vaule in to new array
			foreach($value as $keyLine => $line)
			{
				$result[$keyLine][$key] = trim($line);
			}
		}
		return $result;
	}
    
    /**
     * Remove control characters 
     *
     * @author nerdalertdk (https://github.com/nerdalertdk)
     * @link https://github.com/Nizarii/arma-rcon-class-php/issues/4
     * @return string
     */
	private function cleanList($str)
	{
		return preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $str );
	}
}

