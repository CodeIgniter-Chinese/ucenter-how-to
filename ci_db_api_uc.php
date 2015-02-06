<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
*  Ucenter接口通知处理控制器
*
*  本类根据ucenter提供的通知处理实例代码编写，具体处理部分需要根据不同应用的逻辑自行编写处理逻辑。
*  具体请仔细阅读ucenter自带的手册。
*
*  教程https://github.com/CodeIgniter-Chinese/ucenter-how-to/blob/master/README.md
*  对ci程序和discuz通过ucenter整合在一起做了清楚的介绍，至于ci自带用户表的情况没有介绍。
*
*  该代码就是在此基础上，参考ucenter手册中的示例代码，编写了login，register，logout方法，
*  以举例说明如何整合ci自带用户表的情况，不当之处，欢迎指正。
*
*  @author     yikai.shao<807862588@qq.com>
*/

/**
 * 连接数据库
 * 用户表样例
 CREATE TABLE `example_members` (
   `uid` int(11) NOT NULL COMMENT 'UID',
   `username` char(15) default NULL COMMENT '用户名',
   `admin` tinyint(1) default NULL COMMENT '是否为管理员',
   PRIMARY KEY  (`uid`)
 ) TYPE=MyISAM;

 */
header("Content-type: text/html; charset=utf-8");

class Uc extends CI_Controller
{
    const API_DELETEUSER = 1;
    const API_RENAMEUSER = 1;
    const API_GETTAG = 1;
    const API_SYNLOGIN = 1;
    const API_SYNLOGOUT = 1;
    const API_UPDATEPW = 1;
    const API_UPDATEBADWORDS = 1;
    const API_UPDATEHOSTS = 1;
    const API_UPDATEAPPS = 1;
    const API_UPDATECLIENT = 1;
    const API_UPDATECREDIT = 1;
    const API_GETCREDITSETTINGS = 1;
    const API_GETCREDIT = 1;
    const API_UPDATECREDITSETTINGS = 1;

    const API_RETURN_SUCCEED = 1;
    const API_RETURN_FAILED = -1;
    const API_RETURN_FORBIDDEN = -2;

    public function __construct()
    {
        parent::__construct();
        
        include APPPATH.'config/ucenter.php';
        include './uc_client/client.php';

        $user_info = $this->session->userdata('user');
        if(!empty($user_info['username'])) {
            list($u_id, $user_name) = explode("\t", uc_authcode($user_info['username'], 'DECODE'));
        } else {
            $u_id = $user_name = '';
        }
    }
    public function index()
    {
        
        $get = $post = array();
        $code = $this->input->get('code', true);
        parse_str(self::authcode($code, 'DECODE', UC_KEY), $get);
        $timestamp = time();
        if ($timestamp - $get['time'] > 3600)
        {
            echo '授权已过期';
            return;
        }
        if (empty($get))
        {
            echo '非法请求';
            return;
        }
        $post = self::unserialize(file_get_contents('php://input'));
        if (in_array($get['action'], array(
            'test',
            'deleteuser',
            'renameuser',
            'gettag',
            'synlogin',
            'synlogout',
            'updatepw',
            'updatebadwords',
            'updatehosts',
            'updateapps',
            'updateclient',
            'updatecredit',
            'getcreditsettings',
            'updatecreditsettings')))
        {

            echo $this->$get['action']($get, $post);
            return;
        }
        else
        {
            echo self::API_RETURN_FAILED;
            return;
        }

    }

    public function login()
    {
        /**
         *  登录
         *  @author yikai.shao <807862588@qq.com>
         */
        $user_info = $this->session->userdata('user');
        if(!empty($user_info['username']))
        {
            exit($user_info['username'].'您已经登录，<a href="logout">点击退出</a>');
        }
        if(empty($_POST['submit'])) {
            //登录表单
            echo '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';
            echo '登录:';
            echo '<dl><dt>用户名</dt><dd><input name="username"></dd>';
            echo '<dt>密码</dt><dd><input name="password" type="password"></dd></dl>';
            echo '<input name="submit" type="submit"> ';
            echo '</form>';exit;
        } else {
            //通过接口判断登录帐号的正确性，返回值为数组
            list($uid, $username, $password, $email) = uc_user_login($_POST['username'], $_POST['password']);

            $this->session->sess_destroy();
            if($uid > 0) {
                $sql = 'SELECT count(*) FROM example_members WHERE uid="?"';
                $query = $this->db->query($sql, $uid);

                if(!$query->num_rows()) {
                    //判断用户是否存在于用户表，不存在则跳转到激活页面
                    $auth = rawurlencode(uc_authcode("$username\t".time(), 'ENCODE'));
                    echo '您需要需要激活该帐号，才能进入本应用程序<br><a href="register?action=activation&auth='.$auth.'">继续</a>';
                    exit;
                }
           
                $this->session->set_userdata('user',array(
                        'username' => uc_authcode($uid."\t".$username, 'ENCODE'),
                    ));
                //生成同步登录的代码
                $ucsynlogin = uc_user_synlogin($uid);
                echo '登录成功'.$ucsynlogin.'<br><a href="login">继续</a>';
                exit;
            } elseif($uid == -1) {
                echo '用户不存在,或者被删除';
            } elseif($uid == -2) {
                echo '密码错';
            } else {
                echo '未定义';
            }
        }
    }

    public function register()
    {
        /**
         *  注册
         *  @author yikai.shao <807862588@qq.com>
         */
        if(empty($_POST['submit'])) {
            //注册表单
            echo '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';

            if($_GET['action'] == 'activation') {
                echo '激活:';
                list($activeuser) = explode("\t", uc_authcode($_GET['auth'], 'DECODE'));
                echo '<input type="hidden" name="activation" value="'.$activeuser.'">';
                echo '<dl><dt>用户名</dt><dd>'.$activeuser.'</dd></dl>';
            } else {
                echo '注册:';
                echo '<dl><dt>用户名</dt><dd><input name="username"></dd>';
                echo '<dt>密码</dt><dd><input name="password"></dd>';
                echo '<dt>Email</dt><dd><input name="email"></dd></dl>';
            }
            echo '<input name="submit" type="submit">';
            echo '</form>';
        } else {
            //在UCenter注册用户信息
            $username = '';
            if(!empty($_POST['activation']) && ($activeuser = uc_get_user($_POST['activation']))) {
                list($uid, $username) = $activeuser;
            } else {
                $sql = "SELECT uid FROM example_members WHERE username='$_POST[username]'";
                $query = $this->db->query($sql);
                $res = $query->row();

                
                if(uc_get_user($_POST['username']) && !$res['uid']) {
                    //判断需要注册的用户如果是需要激活的用户，则需跳转到登录页面验证
                    echo '该用户无需注册，请激活该用户<br><a href="'.$_SERVER['PHP_SELF'].'">继续</a>';
                    exit;
                }

                $uid = uc_user_register($_POST['username'], $_POST['password'], $_POST['email']);
                if($uid <= 0) {
                    if($uid == -1) {
                        echo '用户名不合法';
                    } elseif($uid == -2) {
                        echo '包含要允许注册的词语';
                    } elseif($uid == -3) {
                        echo '用户名已经存在';
                    } elseif($uid == -4) {
                        echo 'Email 格式有误';
                    } elseif($uid == -5) {
                        echo 'Email 不允许注册';
                    } elseif($uid == -6) {
                        echo '该 Email 已经被注册';
                    } else {
                        echo '未定义';
                    }
                } else {
                    $username = $_POST['username'];
                }
            }
            if($username) {
                $data = array(
                        'uid' => $uid,
                        'username' => $username,
                        'admin' => '0',
                    );
                $this->db->insert('example_members', $data);

                //注册成功，设置 Cookie，加密直接用 uc_authcode 函数，用户使用自己的函数
                $this->session->set_userdata('user',array(
                        'username' => uc_authcode($uid."\t".$username, 'ENCODE'),
                    ));
                $ucsynlogin = uc_user_synlogin($uid);
                echo '注册成功<br><a href="login">继续</a>'.$ucsynlogin;
                exit;
            }
        }
    }
    public function logout()
    {
        /**
         *  注销
         *  @author yikai.shao <807862588@qq.com>
         */
        $this->session->sess_destroy();
        //生成同步退出的代码
        $ucsynlogout = uc_user_synlogout();
        echo '退出成功'.$ucsynlogout;
        exit;
    }

    private function test($get, $post)
    {
        return self::API_RETURN_SUCCEED;
    }

    private function deleteuser($get, $post)
    {
        if ( ! self::API_DELETEUSER)
        {
            return self::API_RETURN_FORBIDDEN;
        }
        $uids = $get['ids'];
        //delete your users here
        return self::API_RETURN_SUCCEED;
    }

    private function gettag($get, $post)
    {
        if ( ! self::API_GETTAG)
        {
            return self::API_RETURN_FORBIDDEN;
        }
        //
        return self::API_RETURN_SUCCEED;
    }

    private function synlogin($get, $post)
    {
        if ( ! self::API_SYNLOGIN)
        {
            return self::API_RETURN_FORBIDDEN;
        }
        header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
        $uid = $get['uid'];
        //同步登录的代码在这里处理
        // include APPPATH.'../uc_client/client.php';
        if ($uc_user = uc_get_user($uid, 1))
        {
            $this->load->library('session');
            $this->session->set_userdata('user', array(
                'uid' => $uid,
                'username' => $uc_user[1]
            ));
        }

        return self::API_RETURN_SUCCEED;
    }

    private function synlogout($get, $post)
    {
        if ( ! self::API_SYNLOGOUT)
        {
            return self::API_RETURN_FORBIDDEN;
        }
        header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
        $this->session->sess_destroy();
        return self::API_RETURN_SUCCEED;
    }

    private function updatepw($get, $post)
    {
        if ( ! self::API_UPDATEPW)
        {
            return self::API_RETURN_FORBIDDEN;
        }
        //这里做修改密码操作
        return self::API_RETURN_SUCCEED;
    }

    private function updatebadwords($get, $post)
    {
        if ( ! self::API_UPDATEBADWORDS)
        {
            return self::API_RETURN_FORBIDDEN;
        }
        $cachefile = APPPATH.'../uc_client/data/cache/badwords.php';
        @unlink($cachefile);
        return self::API_RETURN_SUCCEED;
    }

    private function updatehosts($get, $post)
    {
        if ( ! self::API_UPDATEHOSTS)
        {
            return self::API_RETURN_FORBIDDEN;
        }
        $cachefile = APPPATH.'../uc_client/data/cache/hosts.php';
        @unlink($cachefile);
        return self::API_RETURN_SUCCEED;
    }

    private function updateapps($get, $post)
    {
        if ( ! self::API_UPDATEAPPS)
        {
            return self::API_RETURN_FORBIDDEN;
        }
        $cachefile = APPPATH.'../uc_client/data/cache/apps.php';
        @unlink($cachefile);
        return self::API_RETURN_SUCCEED;
    }

    private function updateclient($get, $post)
    {
        if ( ! self::API_UPDATECLIENT)
        {
            return self::API_RETURN_FORBIDDEN;
        }
        $cachefile = APPPATH.'../uc_client/data/cache/settings.php';
        @unlink($cachefile);
        return self::API_RETURN_SUCCEED;
    }

    private function updatecredit($get, $post)
    {
        if ( ! self::API_UPDATECREDIT)
        {
            return self::API_RETURN_FORBIDDEN;
        }
        return self::API_RETURN_SUCCEED;
    }

    private function getcredit($get, $post)
    {
        if ( ! self::API_GETCREDIT)
        {
            return self::API_RETURN_FORBIDDEN;
        }
        return self::API_RETURN_SUCCEED;
    }

    public static function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0)
    {
        $ckey_length = 4;
        $key = md5($key ? $key : UC_KEY);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

        $cryptkey = $keya.md5($keya.$keyc);
        $key_length = strlen($cryptkey);

        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
        $string_length = strlen($string);

        $result = '';
        $box = range(0, 255);

        $rndkey = array();
        for($i = 0; $i <= 255; $i++)
        {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for($j = $i = 0; $i < 256; $i++)
        {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for($a = $j = $i = 0; $i < $string_length; $i++)
        {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if($operation == 'DECODE')
        {
            if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16))
            {
                return substr($result, 26);
            }
            else
            {
                return '';
            }
        }
        else
        {
            return $keyc.str_replace('=', '', base64_encode($result));
        }
    }

    public static function serialize($arr, $htmlOn = 0)
    {
        if ( ! function_exists('xml_serialize'))
        {
            require APPPATH.'../uc_client/lib/xml.class.php';
        }
        return xml_serialize($arr, $htmlOn);
    }

    public static function unserialize($xml, $htmlOn = 0)
    {
        if ( ! function_exists('xml_serialize'))
        {
            require APPPATH.'../uc_client/lib/xml.class.php';
        }
        return xml_unserialize($xml, $htmlOn);
    }

    public static function gbk2utf8($string)
    {
        return iconv("GB2312", "UTF-8//IGNORE", $string);
    }

    public static function utf82gbk($string)
    {
        return iconv("UTF-8", "GB2312//IGNORE", $string);
    }

}
