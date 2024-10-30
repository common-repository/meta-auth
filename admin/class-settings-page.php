<?php

/**
 * Settings Page
 *
 * @package MetaAuth\Admin
 */

/**
 * MetaAuth_Settings_Page
 */
final class MetaAuth_Settings_Page
{
    const SLUG = 'meta-auth-settings';

    /**
     * @var string
     */
    const SETTINGS_GROUP = 'meta_auth_settings_group';

    /**
     * @var array
     */
    private $settings;

    /**
     * Singleton
     */
    public static function init()
    {
        static $self = null;

        if (null === $self) {
            $self = new self;
            add_action('admin_menu', array($self, 'add_menu_page'));
            add_action('admin_init', array($self, 'register_setting_group'), 10, 0);
        }
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->settings = array_merge(
            array(
                'min_balance' => 0,
            ),
            (array) get_option('meta_auth_settings')
        );
    }

    /**
     * Add page
     *
     * @return void
     */
    public function add_menu_page()
    {
        $this->hook_name = add_submenu_page('meta-auth-tos', __('Terms of Use', AUTH_PLUGIN), __('Settings', AUTH_PLUGIN), 'manage_options', self::SLUG, array($this, 'render'));
    }

    /**
     * Register setting group
     *
     * @internal Used as a callback
     */
    public function register_setting_group()
    {
        register_setting(self::SETTINGS_GROUP, 'meta_auth_settings', array($this, 'sanitize'));
    }

    /**
     * Sanitize form data
     *
     * @internal Used as a callback
     * @var array $data Submiting data
     */
    public function sanitize(array $data)
    {
        if (!empty($data['min_balance'])) {
            $data['min_balance'] = floatval($data['min_balance']);
        }

        return $data;
    }

    /**
     * Render
     *
     * @internal  Callback.
     */
    public function render($page_data)
    {
        ?>
        <div class="wrap">
            <h1>
                <?= __('Meta Auth Settings', AUTH_PLUGIN); ?>
            </h1>
            <form method="post" action="options.php" novalidate="novalidate">
                <?php settings_fields(self::SETTINGS_GROUP); ?>
                <div class="settings-tab">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?= __('Infura Project API-Key', AUTH_PLUGIN); ?>
                            </th>
                            <td>
                                <input style="width:300px" type="text" name="<?= $this->get_name('infura_project_id') ?>"
                                    value="<?= $this->get_value('infura_project_id') ?>">
                                <p class="description">
                                    <?= __('Get infura project API-KEY by signing up   <a href="https://infura.io/register" target="_blank"> here</a>. Choose <b>Web3 API</b> as <b>network</b> and give a nice <b>name</b> of your choice. Copy the API-KEY from the next window.', AUTH_PLUGIN); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?= __('Shortcode', AUTH_PLUGIN); ?>
                            </th>
                            <td>
                                <input style="color:#2c3338" type="text" value="[meta-auth]" disabled>
                                <p class="description">
                                    <?= __('Read-only. The shortcode to display the login form with 2FA somewhere.', AUTH_PLUGIN); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?= __('Minimum Balance', AUTH_PLUGIN); ?>
                            </th>
                            <td>
                                <input type="number" name="<?= $this->get_name('min_balance'); ?>"
                                    value="<?= $this->get_value('min_balance'); ?>">
                                <p class="description">
                                    <?= __('Minimum required balance to login.', AUTH_PLUGIN); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php submit_button(); ?>
            </form>
            <?php
    }

    /**
     * Get name
     *
     * @param  string $field  Key name.
     *
     * @return  string
     */
    private function get_name($key)
    {
        return 'meta_auth_settings[' . $key . ']';
    }

    /**
     * Get value
     *
     * @param  string $key  Key name.
     *
     * @return  mixed
     */
    private function get_value($key)
    {
        return isset($this->settings[$key]) ? sanitize_text_field($this->settings[$key]) : '';
    }
}

// Singleton.
MetaAuth_Settings_Page::init();