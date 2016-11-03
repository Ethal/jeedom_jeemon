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
    public function cronHourly() {
        foreach (eqLogic::byType('jeemon', true) as $jeemon) {
            $jeemon->checkJeemon('hourly');
        }
    }

    public function cron15() {
        foreach (eqLogic::byType('jeemon', true) as $jeemon) {
            $jeemon->checkJeemon('15');
        }
    }

    public function cronDaily() {
        foreach (eqLogic::byType('jeemon', true) as $jeemon) {
            $jeemon->checkJeemon('daily');
        }
    }

    public function postUpdate() {
        $this->checkCmdOk('backup','Sauvegarde de moins de 24h','binary','daily','');
        $this->checkCmdOk('hdd_space','Espace disque / utilisé','numeric','hourly','%');
        $this->checkCmdOk('tmp_space','Espace disque /tmp utilisé','numeric','hourly','%');
        $this->checkCmdOk('tmp_type','Type de montage /tmp','string','daily','');
        $this->checkCmdOk('uptime','Durée depuis dernier reboot','numeric','15','mn');
        $this->checkCmdOk('cpuload','Charge CPU sur 15mn','numeric','15','');
        $this->checkCmdOk('logerr','Activité sur le log erreurs','binary','15','');
        $this->checkCmdOk('memory','Charge mémoire','numeric','15','%');
        $this->checkJeemon('all');
    }

    public function checkCmdOk($_id, $_name, $_type, $_cron, $_unite) {
        $jeemonCmd = jeemonCmd::byEqLogicIdAndLogicalId($this->getId(),$_id);
        if (!is_object($jeemonCmd)) {
            log::add('jeemon', 'debug', 'Création de la commande ' . $_id);
            $jeemonCmd = new jeemonCmd();
            $jeemonCmd->setName(__($_name, __FILE__));
            $jeemonCmd->setEqLogic_id($this->id);
            $jeemonCmd->setEqType('jeemon');
            $jeemonCmd->setLogicalId($_id);
            $jeemonCmd->setType('info');
            $jeemonCmd->setTemplate("mobile",'line' );
            $jeemonCmd->setTemplate("dashboard",'line' );
            $jeemonCmd->setDisplay("forceReturnLineAfter","1");
        }
        $jeemonCmd->setSubType($_type);
        $jeemonCmd->setUnite($_unite);
        $jeemonCmd->setConfiguration('cron',$_cron);
        $jeemonCmd->save();
    }

    public function getExecCmd($id) {
        switch ($id) {
            case 'backup':
            $backup_path = realpath(dirname(__FILE__) . '/../../../../backup');
            $result = shell_exec('if [ $(find ' . $backup_path . ' -mtime -1 | wc -l) -gt 0 ]; then echo "1"; else echo "0"; fi');
            break;
            case 'logerr':
            $log_path = realpath(dirname(__FILE__) . '/../../../../log');
            if (strpos($_SERVER['SERVER_SOFTWARE'],'Apache') !== false) {
              $file_name = 'http.err';
            } else {
              $file_name = 'nginx-error.log'; //welldone !!!
            }
            $result = shell_exec('if [ $(find ' . $log_path . ' -name ' . $file_name . ' -mmin -15 | wc -l) -gt 0 ]; then echo "1"; else echo "0"; fi');
            break;
            case 'hdd_space':
            $space = shell_exec('sudo df -h / | tail -n 1');
            $pattern = '/([1-9]*?)\%/';
            preg_match($pattern, $space, $matches);
            $result = $matches[1];
            break;
            case 'tmp_space':
            $space = shell_exec('sudo df -h /tmp | tail -n 1');
            $pattern = '/([1-9]*?)\%/';
            preg_match($pattern, $space, $matches);
            $result = $matches[1];
            break;
            case 'tmp_type':
            $result = shell_exec("sudo df -h /tmp | tail -n 1 | awk '{print $1}'");
            break;
            case 'cpuload':
            $result = shell_exec("uptime | awk  '{print $11}'");
            break;
            case 'memory':
            //$used = shell_exec("free -m | grep Mem | cut -f3 -d' '");
            //$total = shell_exec("free -m | grep Mem | cut -f2 -d' '");
            $data = explode("\n", file_get_contents("/proc/meminfo"));
            $meminfo = array();
            foreach ($data as $line) {
            	$explode = explode(":", $line);
            	$meminfo[$explode[0]] = trim(str_replace("kB","",$explode[1]));
            }
            $result = round($meminfo["Active"]/$meminfo["MemTotal"]*100,1);
            break;
            case 'uptime':
            $result = shell_exec("awk  '{print $0/60;}' /proc/uptime");
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
            case 'logerr':
            if ($result == 0) {
                $this->alertCmd('Attention, le log jeedom contient des erreurs');
            }
            break;
            case 'tmp_type':
            if ($result != 'tmpfs') {
                $this->alertCmd('Attention, le répertoire tmp n\'est pas en mémoire');
            }
            break;
        }
    }

    public function alertCmd($alert) {
        if ($this->getConfiguration('alert') != "") {
            $cmdalerte = cmd::byId(str_replace('#','',$this->getConfiguration('alert')));
            $options['title'] = "Alerte Jeedom";
            $options['message'] = $alert;
            $cmdalerte->execCmd($options);
        }
    }

    public function checkJeemon($cron) {
        foreach ($this->getCmd() as $cmd) {
            if ($cron == 'all' || $cron == $cmd->getConfiguration('cron')) {
                $id = $cmd->getLogicalId();
                $result = $this->getExecCmd($id);
                $this->getExecAlert($id,$result);
                log::add('jeemon', 'info', 'Commande ' . $id . ' : ' . $result);
                $this->checkAndUpdateCmd($id, $result);
            }
        }
    }
}

class jeemonCmd extends cmd {
}
