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
        $this->checkCmds();
    }

    public function checkCmds() {
        $this->checkCmdOk('backup','Sauvegarde locale de moins de 24h','binary','daily','');
        //core,market::cloudUpload
        $this->checkCmdOk('cloudbackup','Sauvegarde cloud de moins de 24h','binary','daily','');
        $this->checkCmdOk('hdd_space','Espace disque / utilisé','numeric','hourly','%');
        $this->checkCmdOk('tmp_space','Espace disque /tmp utilisé','numeric','hourly','%');
        $this->checkCmdOk('tmp_type','Type de montage /tmp','string','daily','');
        $this->checkCmdOk('uptime','Durée depuis dernier reboot','string','15','');
        $this->checkCmdOk('cpuload','Charge moyenne CPU sur 15mn','numeric','15','%');
        $this->checkCmdOk('logerr','Activité sur le log erreurs','binary','15','');
        $this->checkCmdOk('memory','Charge mémoire','numeric','15','%');
        $jeemonCmd = jeemonCmd::byEqLogicIdAndLogicalId($this->getId(),'refresh');
        if (!is_object($jeemonCmd)) {
            log::add('jeemon', 'debug', 'Création de la commande refresh');
            $jeemonCmd = new jeemonCmd();
            $jeemonCmd->setName(__('Rafraichir', __FILE__));
            $jeemonCmd->setEqLogic_id($this->id);
            $jeemonCmd->setEqType('jeemon');
            $jeemonCmd->setLogicalId('refresh');
            $jeemonCmd->setType('action');
        }
        $jeemonCmd->setSubType('other');
        $jeemonCmd->save();
        $jeemonCmd = jeemonCmd::byEqLogicIdAndLogicalId($this->getId(),'rapport');
        if (!is_object($jeemonCmd)) {
            log::add('jeemon', 'debug', 'Création de la commande rapport');
            $jeemonCmd = new jeemonCmd();
            $jeemonCmd->setName(__('Rapport', __FILE__));
            $jeemonCmd->setEqLogic_id($this->id);
            $jeemonCmd->setEqType('jeemon');
            $jeemonCmd->setLogicalId('rapport');
            $jeemonCmd->setType('action');
        }
        $jeemonCmd->setSubType('other');
        $jeemonCmd->save();
        $this->checkJeemon('all');
    }


    public function checkInstall() {
        $eqLogic = jeemon::byLogicalId('jeemon','jeemon');
        if (!is_object($eqLogic)) {
            $jeemon = new jeemon();
            $jeemon->setEqType_name('jeemon');
            $jeemon->setLogicalId('jeemon');
            $jeemon->setName('Jeemon');
            $jeemon->save();
        }
        if (strpos($_SERVER['SERVER_SOFTWARE'],'Nginx') !== false) {
             $server = 'nginx-error.log';//welldone !!!
         } else {
             $server = 'http.error';
         }
       config::save('logerr', $server,  'jeemon');
       $this->checkCmds();
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
        $result = '';
        switch ($id) {
            case 'backup':
            $backup_path = realpath(dirname(__FILE__) . '/../../../../backup');
            $result = shell_exec('if [ $(find ' . $backup_path . ' -mtime -1 | wc -l) -gt 0 ]; then echo "1"; else echo "0"; fi');
            break;
            case 'cloudbackup':
		if (config::byKey('market::cloudUpload', 'core')) {	
		    $backup = repo_market::listeBackup();
		    if (strpos($backup[0], date('Y-m-d', time() - 60 * 60 * 24)) !== false || strpos($backup[0], date('Y-m-d')) !== false) {
			$result = 1;
		    } else {
			$result = 0;
		    }
		} else {
			$result = 0;
		}
	    log::add('jeemon', 'debug', 'Cloud ' . $backup[0] . ' ' . date('Y-m-d', time() - 60 * 60 * 24) . ' ' . date('Y-m-d'));
	    break;
            case 'logerr':
            $log_path = realpath(dirname(__FILE__) . '/../../../../log');
            $file_name = config::byKey('logerr', 'jeemon','http.error');
            $result = shell_exec('find ' . $log_path . ' -name ' . $file_name . ' -mmin -15 | wc -l');
        log::add('jeemon', 'debug', 'Log file : ' . $log_path . $file_name . ' ' . $result);    
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
            $core = shell_exec("nproc --all");
            $result = $result / $core * 100;
            break;
            case 'memory':
            $used = shell_exec("cat /proc/meminfo | grep 'Active(anon)' | awk '{print $2}'");
            $total = shell_exec("cat /proc/meminfo | grep 'MemTotal' | awk '{print $2}'");
            $result = round($used/$total*100,1);
            break;
            case 'uptime':
            $ut = strtok(@exec("cat /proc/uptime"), ".");
            $days = sprintf("%2d", ($ut / (3600 * 24)));
            $hours = sprintf("%2d", (($ut % (3600 * 24))) / 3600);
            $min = sprintf("%2d", ($ut % (3600 * 24) % 3600) / 60);
            $sec = sprintf("%2d", ($ut % (3600 * 24) % 3600) % 60);
            $uptime = array($days, $hours, $min, $sec);
            if ($uptime[0] == 0) {
                if ($uptime[1] == 0) {
                    if ($uptime[2] == 0) {
                        $result = $uptime[3] . " s";
                    }
                    else {
                        $result = $uptime[2] . " mn";
                    }
                }
                else {
                    $result = $uptime[1] . " h";
                }
            }
            else {
                $result = $uptime[0] . " jour(s)";
            }
            break;

        }
        return $result;
    }

    public function getExecAlert($id,$result) {
        $return = '';
        switch ($id) {
            case 'backup':
            if ($result == 0) {
                $return = 'Pas de sauvegarde locale depuis 24h';
            }
            break;
            case 'cloudbackup':
            if ($result == 0) {
                $return = 'Pas de sauvegarde cloud depuis 24h';
            }
            break;
            case 'logerr':
            if ($result == 0) {
                $return = 'Attention, le log jeedom contient des erreurs';
            }
            break;
            case 'tmp_type':
            if (!preg_match('/tmpfs/',$result)) {
                $return = 'Attention, le répertoire tmp n\'est pas en mémoire';
            }
            break;
            case 'uptime':
            $result = explode($result,' ');
            if ($result[0] < 15 && $result[0] == "minute(s)") {
                $return = 'Attention, Jeedom a redémarrer il y a moins de 15mn';
            }
            break;
            case 'hdd_space':
            if ($result > 90) {
                $return = 'Attention, l\'espace disque est occupé à ' . $result . '%';
            }
            break;
            case 'tmp_space':
            if ($result > 90) {
                $return = 'Attention, l\'espace tmp est occupé à ' . $result . '%';
            }
            break;
        }
        return $return;
    }

    public function alertCmd($type,$alert) {
        if ($type == 'alert' && $this->getConfiguration('alert') != "") {
            $cmdalerte = cmd::byId(str_replace('#','',$this->getConfiguration('alert')));
            $options['title'] = "Alerte Jeedom";
            $options['message'] = $alert;
            $cmdalerte->execCmd($options);
        }
        if ($type == 'report' && $this->getConfiguration('report') != "") {
            $cmdalerte = cmd::byId(str_replace('#','',$this->getConfiguration('report')));
            $options['title'] = "Rapport Jeedom";
            $options['message'] = $alert;
            $cmdalerte->execCmd($options);
            log::add('jeemon', 'debug', 'Rapport ' . $this->getConfiguration('report') . ' : ' . $alert);
        }
    }

    public function reportJeemon() {
        $report = '';
        foreach ($this->getCmd() as $cmd) {
            if ($cmd->getType() == 'info' && ($cmd->getConfiguration('alert') == 'alert' || $cmd->getConfiguration('alert') == 'report')) {
                $id = $cmd->getLogicalId();
                $result = $this->getExecCmd($id);
                $message = $this->getExecAlert($id,$result);
                switch ($id) {
                    case 'backup':
                    $replace = ($result) ? 'OK' : 'KO';
                    $resmes = 'Sauvegarde locale de moins de 24h : ' . $replace;
                    break;
                    case 'cloudbackup':
                    $replace = ($result) ? 'OK' : 'KO';
                    $resmes = 'Sauvegarde cloud de moins de 24h : ' . $replace;
                    break;
                    case 'logerr':
                    $replace = ($result) ? 'OK' : 'KO';
                    $resmes = 'Présence de nouvelles erreurs dans le fichier de log web : ' . $replace;
                    break;
                    case 'hdd_space':
                    $resmes = 'Espace disque racine occupé : ' . $result . '%';
                    break;
                    case 'tmp_space':
                    $resmes = 'Espace disque /tmp occupé : ' . $result . '%';
                    break;
                    case 'tmp_type':
                    $resmes = 'Type de montage /tmp : ' . $result;
                    break;
                    case 'cpuload':
                    $resmes = 'Taux utilisation CPU : ' . $result . '%';
                    break;
                    case 'memory':
                    $resmes = 'Taux utilisation mémoire : ' . $result . '%';
                    break;
                    case 'uptime':
                    $resmes = 'Dernier reboot : ' . $result;
                    break;

                }
                if ($message != '') {
                    $report .= $resmes . ', ' . $message . PHP_EOL;
                } else {
                    $report .= $resmes . ' ' . PHP_EOL;
                }
                log::add('jeemon', 'debug', 'Rapport ' . $cmd->getLogicalId() . ' : ' . $result . ', ' . $message);
                $this->checkAndUpdateCmd($id, $result);
            }
        }
        $this->alertCmd('report',$report);
    }

    public function checkJeemon($cron) {
        $alert = '';
        foreach ($this->getCmd() as $cmd) {
            if ($cmd->getType() == 'info') {
                if ($cron == 'all' || $cron == $cmd->getConfiguration('cron')) {
                    $id = $cmd->getLogicalId();
                    $result = $this->getExecCmd($id);
                    $message = $this->getExecAlert($id,$result);
                    if ($cmd->getConfiguration('alert') == 'alert' && $message != '') {
                        $alert .= $message . PHP_EOL;
                    }
                    log::add('jeemon', 'debug', 'Commande ' . $id . ' : ' . $result);
                    $this->checkAndUpdateCmd($id, $result);
                }
            }
        }
        if ($alert != '') {
            $this->alertCmd('alert',$alert);
        }
    }

    public function resetAlert() {
        foreach ($this->getCmd() as $cmd) {
            $cmd->setConfiguration('alert','0');
            $cmd->save();
        }
    }
}

class jeemonCmd extends cmd {
    public function execute($_options = null) {
		if ($this->getType() == 'info') {
			return $this->getConfiguration('value');
		} else {
			$eqLogic = $this->getEqLogic();
            if ($this->getLogicalId() == 'rapport') {
                $eqLogic->reportJeemon();
            } else {
                $eqLogic->checkJeemon('all');
            }

		}
	}
}
