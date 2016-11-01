<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class jeemon extends eqLogic {
    public function cron() {
        foreach (eqLogic::byType('jeemon', true) as $jeemon) {
            $jeemon->readStatus($jeemon->getConfiguration('addr'));
        }
    }

    public function postUpdate() {
        $this->checkCmdOk('backup','Sauvegarde de moins de 24h','binary');
        $this->checkCmdOk('space','Espace disque utilisé','numeric');
        //log ERROR
        //tmp state
        //memory
        //cpu
        //uptime
        $this->checkJeemon();
    }

    public function checkCmdOk($_id, $_name, $_type) {
        $jeemonCmd = jeemonCmd::byEqLogicIdAndLogicalId($this->getId(),$_id);
        if (!is_object($jeemonCmd)) {
            log::add('jeemon', 'debug', 'Création de la commande ' . $_id);
            $jeemonCmd = new jeemonCmd();
            $jeemonCmd->setName(__($_name, __FILE__));
            $jeemonCmd->setEqLogic_id($this->id);
            $jeemonCmd->setEqType('jeemon');
            $jeemonCmd->setLogicalId($_id);
            $jeemonCmd->setType('info');
            $jeemonCmd->setSubType($_type);
            $jeemonCmd->setTemplate("mobile",'line' );
            $jeemonCmd->setTemplate("dashboard",'line' );
            $jeemonCmd->setDisplay("forceReturnLineAfter","1");
            $jeemonCmd->save();
        }
    }

    public function getExecCmd($id) {
        switch ($id) {
            case 'backup':
            $result = shell_exec('if find /usr/share/nginx/www/jeedom/backup -mtime -1 | read; then echo "1"; else echo "0"; fi');
            break;
            case 'space':
            $space = shell_exec('sudo df -h / | tail -n 1');
            $pattern = '/([1-9]*?)\%/';
            preg_match($pattern, $space, $matches);
            $result = $matches[1];
            break;
        }
        return $result;
    }

    public function getExecAlert($id,$result) {
        switch ($id) {
            case 'backup':
            if ($result == 0) {
                $this->alertCmd('Pas de sauvegarde depuis 24h');
            }
            break;
        }
        return $result;
    }

    public function alertCmd($alert) {
        if ($this->getConfiguration('alert') != "") {
            $alert = str_replace('#','',$this->getConfiguration('alert'));
            $cmdalerte = cmd::byId($alert);
            $options['title'] = "Alerte Jeedom";
            $options['message'] = $alert;
            $cmdalerte->execCmd($options);
        }
    }

    public function checkJeemon() {
        foreach ($this->getCmd() as $cmd) {
            $id = $cmd->getLogicalId();
            $result = $this->getExecCmd($id);
            $this->alertCmd($id,$result);
            log::add('jeemon', 'info', 'Commande ' . $id . ' : ' . $result);
            $this->checkAndUpdateCmd($id, $result);
        }
    }
}

class jeemonCmd extends cmd {
}
