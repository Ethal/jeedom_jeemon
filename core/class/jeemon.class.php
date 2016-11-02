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
            $jeemon->checkJeemon();
        }
    }

    public function postUpdate() {
        $this->checkCmdOk('backup','Sauvegarde de moins de 24h','binary');
        $this->checkCmdOk('hdd_space','Espace disque / utilisé','numeric');
        $this->checkCmdOk('tmp_space','Espace disque /tmp utilisé','numeric');
        $this->checkCmdOk('tmp_type','Type de montage /tmp','string');
        $this->checkCmdOk('uptime','Durée depuis dernier reboot','numeric');
        $this->checkCmdOk('cpuload','Charge CPU sur 15mn','numeric');
        //log ERROR
        //memory
        //cpu
        //uptime
        /*
        for folder in php5 php7; do
		for subfolder in apache2 cli; do
	    	if [ -f /etc/${folder}/${subfolder}/php.ini ]; then
	    		echo "Update php file /etc/${folder}/${subfolder}/php.ini"
				sed -i 's/max_execution_time = 30/max_execution_time = 300/g' /etc/${folder}/${subfolder}/php.ini > /dev/null 2>&1
			    sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 1G/g' /etc/${folder}/${subfolder}/php.ini > /dev/null 2>&1
			    sed -i 's/post_max_size = 8M/post_max_size = 1G/g' /etc/${folder}/${subfolder}/php.ini > /dev/null 2>&1
			    sed -i 's/expose_php = On/expose_php = Off/g' /etc/${folder}/${subfolder}/php.ini > /dev/null 2>&1
			    sed -i 's/;opcache.enable=0/opcache.enable=1/g' /etc/${folder}/${subfolder}/php.ini > /dev/null 2>&1
			    sed -i 's/opcache.enable=0/opcache.enable=1/g' /etc/${folder}/${subfolder}/php.ini > /dev/null 2>&1
			    sed -i 's/;opcache.enable_cli=0/opcache.enable_cli=1/g' /etc/${folder}/${subfolder}/php.ini > /dev/null 2>&1
			    sed -i 's/opcache.enable_cli=0/opcache.enable_cli=1/g' /etc/${folder}/${subfolder}/php.ini > /dev/null 2>&1
	    	fi
		done
        done*/
        /*
        if [ -d /etc/mysql/conf.d ]; then
    	touch /etc/mysql/conf.d/jeedom_my.cnf
    	echo "[mysqld]" >> /etc/mysql/conf.d/jeedom_my.cnf
    	echo "key_buffer_size = 16M" >> /etc/mysql/conf.d/jeedom_my.cnf
		echo "thread_cache_size = 16" >> /etc/mysql/conf.d/jeedom_my.cnf
		echo "tmp_table_size = 48M" >> /etc/mysql/conf.d/jeedom_my.cnf
		echo "max_heap_table_size = 48M" >> /etc/mysql/conf.d/jeedom_my.cnf
		echo "query_cache_type =1" >> /etc/mysql/conf.d/jeedom_my.cnf
		echo "query_cache_size = 16M" >> /etc/mysql/conf.d/jeedom_my.cnf
		echo "query_cache_limit = 2M" >> /etc/mysql/conf.d/jeedom_my.cnf
		echo "query_cache_min_res_unit=3K" >> /etc/mysql/conf.d/jeedom_my.cnf
		echo "innodb_flush_method = O_DIRECT" >> /etc/mysql/conf.d/jeedom_my.cnf
		echo "innodb_flush_log_at_trx_commit = 2" >> /etc/mysql/conf.d/jeedom_my.cnf
		echo "innodb_log_file_size = 32M" >> /etc/mysql/conf.d/jeedom_my.cnf
        fi*/
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
            $backup_path = realpath(dirname(__FILE__) . '/../../../../backup');
            $result = shell_exec('if `sudo find ' . $backup_path . ' -mtime -1 | read`; then echo "1"; else echo "0"; fi');
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
            $uptime_string = shell_exec('uptime');
            $pattern = '/load average: (.*), (.*), (.*)$/';
    		preg_match($pattern, $uptime_string, $matches);
            $result = $matches[3];
            break;
            case 'uptime':
            $uptime_string = shell_exec('uptime');
            $pattern = '/up (.*?),/';
    		preg_match($pattern, $uptime_string, $matches);
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
    }

    public function alertCmd($alert) {
        if ($this->getConfiguration('alert') != "") {
            $cmdalerte = cmd::byId(str_replace('#','',$this->getConfiguration('alert')));
            $options['title'] = "Alerte Jeedom";
            $options['message'] = $alert;
            $cmdalerte->execCmd($options);
        }
    }

    public function checkJeemon() {
        foreach ($this->getCmd() as $cmd) {
            $id = $cmd->getLogicalId();
            $result = $this->getExecCmd($id);
            $this->getExecAlert($id,$result);
            log::add('jeemon', 'info', 'Commande ' . $id . ' : ' . $result);
            $this->checkAndUpdateCmd($id, $result);
        }
    }
}

class jeemonCmd extends cmd {
}
