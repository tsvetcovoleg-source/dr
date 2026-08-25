<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
use DecisionRules\Database;use DecisionRules\UserRepository;
if($argc<3){fwrite(STDERR,"Usage: php bin/create-admin.php <username> <full-name>\nPassword is read securely from the terminal.\n");exit(1);}
fwrite(STDOUT,'Password (minimum 12 characters): ');$password=trim((string)shell_exec('stty -echo; head -n 1; stty echo'));fwrite(STDOUT,"\n");if(strlen($password)<12){fwrite(STDERR,"Password is too short.\n");exit(1);}$username=$argv[1];if(!preg_match('/^[A-Za-z0-9_.-]{3,64}$/',$username)){fwrite(STDERR,"Invalid username.\n");exit(1);}$repo=new UserRepository(Database::connect(dirname(__DIR__)));try{$id=$repo->create(['username'=>$username,'full_name'=>$argv[2],'password'=>$password,'roles'=>['ADMIN'],'active'=>true]);fwrite(STDOUT,"ADMIN created with ID $id. Password change is required on first login.\n");}catch(Throwable $e){fwrite(STDERR,"Unable to create ADMIN (does the username already exist?).\n");exit(1);}
