<?php
    declare(strict_types=1);
    use onus\mail;

    /**
     * Class that handles the Accounts Interactions
     */
    class error
    {
        /**
         * Constructor Function, at this point the function only constructs the
         * abstract Parent Class.
         * @param dataSource $dataSource the object that will actually retrieve
         * the information from the databases.
         */
        public function __construct(dataSource $dataSource = NULL, bool $test = FALSE)
        {
            parent::__construct($dataSource,$test);
        }

    	public function errorHandler(string $num, string $mess, string $file)
    	{
    		$params = array(
    			'num' => $num,
    			'mess' => $mess,
    			'file' => $file,
    		);
    		$this->sendError(0,$params);
    	}

        /**
         * Function to create and Send and Error if necessary
         *
         * @param int $kind the type of error
         * @param array $data all the data involved with the error
         * @return bool $status the error was sended and created correctly
         */
        public function sendError(int $kind,array $data)
        {
            $status = FALSE;
            $errorID=0;
            $time = date('Y-m-d H:i:s');
            $errorID = $this->newError($kind, $data, $time)[0];
            return $status;
        }

        /**
         * Function to create all the information Previous the mail
         *
         * @param int $kind the type of error
         * @param array $data all the data involved with the error
         * @param string $time the time and date the error happend
         * @param int $errorID the id of the created error in the DB
         * @return bool $status the mail was sended
         */
        private function errorMail(int $kind,array $data,string $time,int $errorID)
        {
            $status = FALSE;
            $receiver = 'email@email.com';
            $subject = $this->setSubject($time, $errorID);
            $message = $this->getMessage($kind, $data, $time);
            if((new mail($this->dataSource, $this->test))->sendMail($receiver,$subject,$message)){
                $status=TRUE;
            }
            return $status;

        }

        /**
         * Function to set the subject for the mail
         *
         * @param string $time the time and date the error happend
         * @param int $errorID the id of the created error in the DB
         * @return string $subject the subject for the to-send mail
         */
        private function setSubject(string $time,int $errorID)
        {
            if (environment=='test'){
                $subject='(TEST ENVIRONMENT) ';
            }

            $subject .= 'Error Message No. '.$errorID.' generated at '.$time;
            return $subject;
        }

        /**
         * Function to do all the Previouswork before the creation of the error
         *
         * @param int $kind the type of error
         * @param array $data all the data involved with the error
         * @param string $time the time and date the error happend
         * @return int $id the id of the created error
         */
        private function newError(int $kind,array $data,string $time)
        {
            $errorID = 0;
            $message = $this->errorType($kind, $data);
            $id = $this->createError($kind, $message, $time);
            return $id;
        }

        /**
         * Function to create the message for the error in the DB
         *
         * @param int $kind the type of error
         * @param array $data all the data involved with the error
         * @return string $message the message the error will have in the DB
         */
        public function errorType(int $kind,array $data)
        {
            $message = '';
            if(isset($_SESSION['Username'])){
                $message = 'User '.$_SESSION['Username'].'created';
            }
            switch ($kind) {
                case 0:
                    $message = 'Error Message; Num;'.$data['num'].', Message:'.$data['mess'];
                    $message .= ', File:'.$data['file'];
                    break;

            }
            return $message;
        }

        /**
         * Function to create the New Error
         *
         * @param int $kind the type of error
         * @param string $message the message of the error
         * @param string $time the time and date the error happend
         * @return array $id & $status
         */
        public function createError(int $kind,string $message, string $time)
        {
            $id = NULL; $status = FALSE;
            $this->setCreateData();

            $create = new operations\create($this->dataSource, $this->test);
            $create->setFields($this->fields);
            $create->setTable($this->table);
            $create->setValues(implode(',',$this->getValues($kind,$message,$time)));
            if($this->test){
                return $create->run();
            }
            if ($create->run()) {
                $id = $this->getNewRecordId($time);
                $status = TRUE;
            }
            return array($id,$status);
        }

        /**
         * Function to set data to be searched.
         *
         * @return void
         */
        protected function setCreateData()
        {
            $fields = 'code,status,message,createUser,createDate';
            $this->fields = $fields;
            $this->table = 'ERR_001';
        }

        /**
         * Function to order the vaules for the sql
         *
         * @param int $kind the type of error
         * @param string $message the message of the error
         * @param string $time the time and date the error happend
         * @return array $values
         */
        public function getValues(int $kind,string $message, string $time)
        {
            $user = isset($_SESSION['Username']) ? $_SESSION['Username'] : 'unknown';
            $values = array(
                '\''.$kind.'\'',
                '\''.$message.'\'',
                '\''.$user.'\'',
                '\''.$time.'\''
                        );
            return $values;
        }

        /**
         * Function to set data to retrieve the identifier from the new Customer
         *
         * @param string $time the date-time when it was created
         * @return void
         */
        protected function setNewRecordData(string $time)
        {
            $user = isset($_SESSION['Username']) ? $_SESSION['Username'] : 'unknown';
            $whereClause = 'CreateUser=\''.$user.'\' AND ';
            $whereClause .= 'CreateDate=\''.$time.'\'';
            $this->fields = 'ID AS ID';
            $this->table = 'Error';
            $this->whereClause = $whereClause;
        }

        /**
         * Function to get the html for the mail
         *
         * @param int $kind the type of error
         * @param array $data all the data involved with the error
         * @param string $time the time and date the error happend
         * @return string $message the html for the message
         */
        private function getMessage(int $kind,array $data,string $time)
        {
            $message = $this->messageType($kind, $data, $time);

            $message .= '
                    <li>Server Name: '.$_SERVER['SERVER_NAME'].'</li>
                    <li>Request Method: '.$_SERVER['REQUEST_METHOD'].'</li>
                    <li>Request Time: '.$_SERVER['REQUEST_TIME'].'</li>
                    <li>User Agent: '.$_SERVER['HTTP_USER_AGENT'].'</li>
                    <li>Remote Address: '.$_SERVER['REMOTE_ADDR'].'</li>
                    <li>Server Admin: '.$_SERVER['SERVER_ADMIN'].'</li>
                    <li>Date & Time: '.$time.'</li>
                </ul><br>
            <i>(Please note this is a notification only. Do not respond to this e-mail.)</i>
            </div>
            </body>
            </html>';
            return $message;

        }

        /**
         * Function to assign the type of message type
         *
         * @param int $kind the type of error
         * @param array $data all the data involved with the error
         * @param string $time the time and date the error happend
         * @param string $message the message you'll be sending to
         * @return bool $status the mail was sended
         */
        private function messageType(int $kind,array $data,string $time)
        {
            $user = isset($_SESSION['Username']) ? $_SESSION['Username'] : 'unknown';
            $message = '<html>
                <head>
                    <title>New Error Message</title>
                  </head>
                <body>
                <div class="container"><br><br> User '.$user;
            switch ($kind) {
                case 0:
                $message .= '<br><ul>
                            <li>Error Num: '.$data['num'].'</li>';
                    break;
            }
            return $message;
        }
    }
