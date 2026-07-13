<?php
/**
 * Plugin Name: Linux DO Login for Zibll
 * Plugin URI: https://linux.do/
 * Description: 为子比主题添加 Linux DO Connect 登录按钮，并完美对接子比主题"第三方登录"功能（自动创建新用户 / 用户自行绑定或创建新用户）。
 * Version: 1.1.0
 * Author: <a href="https://github.com/loneshu7" target="_blank" rel="noopener noreferrer">ishu</a>
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) exit;

class LDLZ_Plugin {
    const OPT       = 'ldlz_options';
    const STATE_KEY = 'ldlz_oauth_state_';
    const TYPE      = 'linuxdo'; // 第三方类型标识，所有 user_meta 都以 oauth_linuxdo_* 形式保存，与子比保持一致
    const VERSION   = '1.1.0';   // 与插件头部 Version 保持一致，用于前台资源版本号

    public static function init() {
        add_action('admin_menu',       [__CLASS__, 'admin_menu']);
        add_action('admin_init',       [__CLASS__, 'register_settings']);
        add_action('rest_api_init',    [__CLASS__, 'register_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('login_message',    [__CLASS__, 'wp_login_button']);

        // 让子比的"社交登录类型表"也认识 linuxdo（用于绑定页标题、解绑面板等）
        add_filter('zib_get_social_type_data', [__CLASS__, 'register_social_type'], 10, 1);
    }

    public static function defaults() {
        return [
            'client_id'     => '',
            'client_secret' => '',
            'authorize_url' => 'https://connect.linux.do/oauth2/authorize',
            'token_url'     => 'https://connect.linux.do/oauth2/token',
            'user_url'      => 'https://connect.linux.do/api/user',
            'scope'         => 'openid profile email',
            'button_title'  => 'Linux DO 登录',
        ];
    }

    public static function opt($key = null) {
        $opts = wp_parse_args(get_option(self::OPT, []), self::defaults());
        return $key === null ? $opts : ($opts[$key] ?? null);
    }

    /* ============================================================
     *  后台设置页
     * ============================================================ */
    public static function admin_menu() {
        add_options_page('Linux DO 登录', 'Linux DO 登录', 'manage_options', 'ldlz', [__CLASS__, 'settings_page']);
    }

    public static function register_settings() {
        register_setting('ldlz_group', self::OPT, [__CLASS__, 'sanitize_options']);
    }

    public static function sanitize_options($in) {
        $d   = self::defaults();
        $out = [];
        foreach ($d as $k => $v) {
            $out[$k] = isset($in[$k]) ? sanitize_text_field($in[$k]) : $v;
        }
        foreach (['authorize_url', 'token_url', 'user_url'] as $k) {
            $out[$k] = esc_url_raw($out[$k]);
        }
        return $out;
    }

    public static function settings_page() {
        $o        = self::opt();
        $callback = rest_url('linuxdo-login/v1/callback');
        $bind_type = function_exists('_pz') ? _pz('oauth_bind_type', 'auto') : 'auto';
        $bind_type_text = $bind_type === 'page' ? '用户自行绑定或创建新用户' : '自动创建新用户';
        ?>
        <div class="wrap">
            <h1>Linux DO 登录 for Zibll</h1>
            <p>把下面这个回调地址填到 Linux DO Connect 应用后台：</p>
            <p><input type="text" class="regular-text code" style="width:680px;max-width:100%;" readonly
                      value="<?php echo esc_attr($callback); ?>" onclick="this.select();"></p>

            <div class="notice notice-info inline" style="margin:12px 0;padding:10px 12px;">
                <p style="margin:0;">
                    当前子比主题"新用户绑定模式"为：<b><?php echo esc_html($bind_type_text); ?></b>。
                    本插件已和子比该设置完美对接，无需在此处单独设置自动注册开关。
                    如需修改，请前往：<b>子比主题设置 → 用户&amp;互动 → 第三方登录 → 新用户绑定模式</b>。
                </p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('ldlz_group'); ?>
                <table class="form-table" role="presentation">
                    <tr><th>Client ID</th>
                        <td><input name="<?php echo self::OPT; ?>[client_id]" class="regular-text"
                                   value="<?php echo esc_attr($o['client_id']); ?>"></td></tr>
                    <tr><th>Client Secret</th>
                        <td><input name="<?php echo self::OPT; ?>[client_secret]" class="regular-text" type="password"
                                   value="<?php echo esc_attr($o['client_secret']); ?>"></td></tr>
                    <tr><th>授权地址</th>
                        <td><input name="<?php echo self::OPT; ?>[authorize_url]" class="regular-text"
                                   value="<?php echo esc_attr($o['authorize_url']); ?>"></td></tr>
                    <tr><th>Token 地址</th>
                        <td><input name="<?php echo self::OPT; ?>[token_url]" class="regular-text"
                                   value="<?php echo esc_attr($o['token_url']); ?>"></td></tr>
                    <tr><th>用户信息地址</th>
                        <td><input name="<?php echo self::OPT; ?>[user_url]" class="regular-text"
                                   value="<?php echo esc_attr($o['user_url']); ?>"></td></tr>
                    <tr><th>Scope</th>
                        <td><input name="<?php echo self::OPT; ?>[scope]" class="regular-text"
                                   value="<?php echo esc_attr($o['scope']); ?>"></td></tr>
                    <tr><th>按钮标题</th>
                        <td><input name="<?php echo self::OPT; ?>[button_title]" class="regular-text"
                                   value="<?php echo esc_attr($o['button_title']); ?>"></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /* ============================================================
     *  把 linuxdo 注册进子比的"社交类型表"
     * ============================================================ */
    public static function register_social_type($args) {
        if (!is_array($args)) {
            $args = [];
        }
        $args[self::TYPE] = [
            'name'     => 'Linux DO',
            'type'     => self::TYPE,
            'class'    => '',
            'name_key' => 'name',
            'icon'     => 'fa fa-sign-in', // 用通用图标，按钮本身用 jpg 背景显示
        ];
        return $args;
    }

    /* ============================================================
     *  REST 路由：/start 与 /callback
     * ============================================================ */
    public static function register_routes() {
        register_rest_route('linuxdo-login/v1', '/start', [
            'methods' => 'GET', 'callback' => [__CLASS__, 'start'], 'permission_callback' => '__return_true',
        ]);
        register_rest_route('linuxdo-login/v1', '/callback', [
            'methods' => 'GET', 'callback' => [__CLASS__, 'callback'], 'permission_callback' => '__return_true',
        ]);
    }

    public static function start($req) {
        $o = self::opt();
        if (empty($o['client_id']) || empty($o['client_secret'])) {
            return self::error_page('Linux DO 登录尚未配置 Client ID / Client Secret。');
        }

        // 启用 session，让子比读取 oauth_rurl
        self::maybe_start_session();

        $redirect_to = self::safe_redirect_url($req->get_param('redirect_to'));

        // 保存子比绑定页跳转回的地址（与子比 github/login.php 中行为一致）
        $_SESSION['oauth_rurl'] = $redirect_to;

        $state = wp_generate_password(32, false, false);
        set_transient(self::STATE_KEY . $state, $redirect_to, 10 * MINUTE_IN_SECONDS);

        $args = [
            'client_id'     => $o['client_id'],
            'redirect_uri'  => rest_url('linuxdo-login/v1/callback'),
            'response_type' => 'code',
            'scope'         => $o['scope'],
            'state'         => $state,
        ];
        wp_redirect(add_query_arg($args, $o['authorize_url']));
        exit;
    }

    public static function callback($req) {
        $code  = sanitize_text_field($req->get_param('code'));
        $state = sanitize_text_field($req->get_param('state'));
        if (!$code || !$state) return self::error_page('Linux DO 回调缺少 code 或 state。');

        $redirect_to = get_transient(self::STATE_KEY . $state);
        delete_transient(self::STATE_KEY . $state);
        if (!$redirect_to) return self::error_page('登录状态已过期，请返回网站重新点击 Linux DO 登录。');

        $o = self::opt();

        // 1) 用 code 换 access_token
        $token_resp = wp_remote_post($o['token_url'], [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json'],
            'body'    => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => rest_url('linuxdo-login/v1/callback'),
                'client_id'     => $o['client_id'],
                'client_secret' => $o['client_secret'],
            ],
        ]);
        if (is_wp_error($token_resp)) {
            return self::error_page('获取 Token 失败：' . $token_resp->get_error_message());
        }
        $token_body = wp_remote_retrieve_body($token_resp);
        if (wp_remote_retrieve_response_code($token_resp) < 200 || wp_remote_retrieve_response_code($token_resp) >= 300) {
            return self::error_page('获取 Token 失败：Linux DO 返回 HTTP ' . wp_remote_retrieve_response_code($token_resp));
        }

        $token = json_decode($token_body, true);
        if (!is_array($token) || empty($token['access_token'])) {
            return self::error_page('Linux DO 未返回有效 access_token。');
        }

        // 2) 拉取用户信息
        $user_resp = wp_remote_get($o['user_url'], [
            'timeout' => 20,
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token['access_token'],
            ],
        ]);
        if (is_wp_error($user_resp)) {
            return self::error_page('获取用户信息失败：' . $user_resp->get_error_message());
        }
        if (wp_remote_retrieve_response_code($user_resp) < 200 || wp_remote_retrieve_response_code($user_resp) >= 300) {
            return self::error_page('获取用户信息失败：Linux DO 返回 HTTP ' . wp_remote_retrieve_response_code($user_resp));
        }

        $info = json_decode(wp_remote_retrieve_body($user_resp), true);
        if (!$info || !is_array($info)) {
            return self::error_page('Linux DO 用户信息解析失败。');
        }

        // 3) 标准化为子比要求的字段
        $u = self::normalize_user($info);
        if (empty($u['id'])) {
            return self::error_page('Linux DO 用户信息缺少用户 ID。');
        }

        // 4) 启用 session 并保存返回地址（子比 oauth_rurl 机制）
        self::maybe_start_session();
        if (empty($_SESSION['oauth_rurl'])) {
            $_SESSION['oauth_rurl'] = $redirect_to;
        }

        // 5) 组装子比标准 oauth_data，交给子比的核心函数处理
        //    —— 这一步即是和子比"新用户绑定模式"开关形成闭环的关键 ——
        $oauth_data = [
            'type'        => self::TYPE,            // 'linuxdo'
            'openid'      => (string) $u['id'],
            'name'        => (string) $u['name'],
            'avatar'      => (string) $u['avatar'],
            'description' => '',
            'getUserInfo' => $info,                  // 原始信息，子比会写入 oauth_linuxdo_getUserInfo
        ];

        if (!function_exists('zib_oauth_update_user')) {
            return self::error_page('未检测到子比主题，或当前主题版本不支持第三方登录对接。');
        }

        // 代理登录（如启用）
        if (function_exists('zib_agent_callback')) {
            zib_agent_callback($oauth_data);
        }

        // 把核心交给子比：
        //  - 已绑定 → 直接登录
        //  - 已登录用户 → 进行绑定
        //  - 全新用户 → 根据后台 "oauth_bind_type"：
        //        auto → 自动创建账号并登录
        //        page → 内部 header 跳转到 ?tab=oauth 绑定页（由用户手动绑定/创建）
        $result = zib_oauth_update_user($oauth_data);

        // 注意：page 模式下，zib_oauth_update_user 内部已经 header+exit，不会走到这里
        if (!empty($result['error'])) {
            return self::error_page($result['msg'] ?: '登录失败');
        }

        // auto 模式：登录成功后，优先用 session 里的 oauth_rurl，再退到子比的 redirect_url
        $rurl = !empty($_SESSION['oauth_rurl']) ? $_SESSION['oauth_rurl'] : ($result['redirect_url'] ?: home_url('/'));
        wp_safe_redirect(self::safe_redirect_url($rurl));
        exit;
    }


    /**
     * 启动 PHP session，避免重复调用和因 headers 已发送导致的警告。
     */
    private static function maybe_start_session() {
        if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }
        session_start();
    }

    /**
     * 只允许跳转回本站 URL，避免 redirect_to 被构造为开放重定向。
     */
    private static function safe_redirect_url($url) {
        $url = is_string($url) ? $url : '';
        return wp_validate_redirect(esc_url_raw($url), home_url('/'));
    }

    /* ============================================================
     *  Linux DO 用户信息标准化
     * ============================================================ */
    private static function normalize_user($info) {
        $data   = $info['data'] ?? $info['user'] ?? $info;
        $id     = $data['id']     ?? $data['sub']        ?? $data['uid']         ?? $data['user_id'] ?? '';
        $name   = $data['name']   ?? $data['nickname']   ?? $data['display_name'] ?? $data['username'] ?? $data['login'] ?? '';
        $avatar = $data['avatar'] ?? $data['avatar_url'] ?? $data['picture']     ?? '';
        return [
            'id'     => (string) $id,
            'name'   => (string) $name,
            'avatar' => (string) $avatar,
        ];
    }

    /* ============================================================
     *  前台资源 & 按钮
     * ============================================================ */
    public static function enqueue_assets() {
        if (is_user_logged_in()) return;
        $start = rest_url('linuxdo-login/v1/start');
        $logo  = plugin_dir_url(__FILE__) . 'assets/linuxdo-logo.jpg';

        wp_register_style('ldlz-style', false, [], self::VERSION);
        wp_enqueue_style('ldlz-style');
        wp_add_inline_style('ldlz-style', self::css($logo));

        wp_register_script('ldlz-script', '', [], self::VERSION, true);
        wp_enqueue_script('ldlz-script');
        wp_add_inline_script('ldlz-script',
            'window.LDLZ=' . wp_json_encode([
                'start' => $start,
                'title' => self::opt('button_title'),
                'logo'  => $logo,
            ]) . ';' . self::js()
        );
    }

    private static function css($logo) {
        return ".social_loginbar .social-login-item.linuxdo{background:#292929!important;background-image:url('"
            . esc_url($logo) . "')!important;background-size:100% 100%!important;background-position:center!important;"
            . "background-repeat:no-repeat!important;color:transparent!important;overflow:hidden;"
            . "box-shadow:0 6px 18px rgba(0,0,0,.12);border:0!important}"
            . ".social_loginbar .social-login-item.linuxdo i,.social_loginbar .social-login-item.linuxdo svg{display:none!important}"
            . ".social_loginbar .social-login-item.linuxdo:hover{transform:translateY(-2px);filter:brightness(1.05)}"
            . ".social_loginbar .social-login-item.linuxdo.button-lg{background-size:32px 32px!important;"
            . "background-position:18px center!important;color:inherit!important;padding-left:58px!important}"
            . ".social_loginbar .social-login-item.linuxdo.button-lg:after{content:'Linux DO登录';color:#fff}"
            . ".ldlz-wp-login{text-align:center;margin:16px 0}"
            . ".ldlz-wp-login a{display:inline-flex;align-items:center;gap:8px;text-decoration:none}"
            . ".ldlz-wp-login img{width:28px;height:28px;border-radius:50%}";
    }

    private static function js() {
        return "(function(){function add(){if(!window.LDLZ)return;"
            . "document.querySelectorAll('.social_loginbar').forEach(function(bar){"
            . "if(bar.querySelector('.social-login-item.linuxdo'))return;"
            . "var a=document.createElement('a');a.rel='nofollow';"
            . "a.title=window.LDLZ.title||'Linux DO登录';"
            . "a.className='social-login-item linuxdo toggle-radius';"
            . "a.href=window.LDLZ.start+'?redirect_to='+encodeURIComponent(location.href);"
            . "a.setAttribute('aria-label',a.title);bar.appendChild(a);});}"
            . "document.addEventListener('DOMContentLoaded',add);"
            . "new MutationObserver(add).observe(document.documentElement,{childList:true,subtree:true});})();";
    }

    public static function wp_login_button($msg) {
        if (is_user_logged_in()) return $msg;
        $href = esc_url(rest_url('linuxdo-login/v1/start') . '?redirect_to=' . rawurlencode(home_url('/')));
        $logo = esc_url(plugin_dir_url(__FILE__) . 'assets/linuxdo-logo.jpg');
        return $msg . '<div class="ldlz-wp-login"><a href="' . $href . '"><img src="' . $logo
            . '" alt="">使用 Linux DO 登录</a></div>';
    }

    private static function error_page($msg) {
        // 优先使用子比自带的错误页，风格统一
        if (function_exists('zib_oauth_die')) {
            zib_oauth_die(esc_html($msg));
            exit;
        }
        wp_die('<h1>Linux DO 登录失败</h1><p>' . esc_html($msg)
            . '</p><p><a href="' . esc_url(home_url('/')) . '">返回首页</a></p>',
            'Linux DO 登录失败', ['response' => 400]);
    }
}
LDLZ_Plugin::init();
