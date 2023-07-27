<?php
namespace Mos_Theme_Options;
class Options_Panel {

    /**
     * Options panel arguments.
     */
    protected $args = [];

    /**
     * Options panel title.
     */
    protected $title = '';

    /**
     * Options panel slug.
     */
    protected $slug = '';

    /**
     * Option name to use for saving our options in the database.
     */
    protected $option_name = '';

    /**
     * Option group name.
     */
    protected $option_group_name = '';

    /**
     * User capability allowed to access the options page.
     */
    protected $user_capability = '';

    /**
     * Our array of settings.
     */
    protected $settings = [];

    /**
     * Our class constructor.
     */
    public function __construct( array $args, array $settings ) {
        $this->args              = $args;
        $this->settings          = $settings;
        $this->page_title        = $this->args['page_title'] ?? esc_html__( 'Options', 'text_domain' );
        $this->menu_title        = $this->args['menu_title'] ?? esc_html__( 'Options', 'text_domain' );
        $this->menu_position     = $this->args['menu_position'] ?? null;
        $this->icon_url          = $this->args['icon_url'] ?? 'dashicons-admin-generic';
        $this->parent_slug       = $this->args['parent_slug'] ?? '';
        $this->slug              = $this->args['slug'] ?? sanitize_key( $this->menu_title );
        $this->option_name       = $this->args['option_name'] ?? sanitize_key( $this->menu_title );
        $this->option_group_name = $this->option_name . '_group';
        $this->user_capability   = $args['user_capability'] ?? 'manage_options';

        add_action( 'admin_menu', [ $this, 'register_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        update_option( 'mos-theme-option-css-output-name', $this->option_name.'-css-output' );
    }

    /**
     * Register the new menu page.
     */
    public function register_menu_page() {
        if ($this->parent_slug) {
            add_submenu_page( 
                $this->parent_slug,           
                $this->page_title,
                $this->menu_title,
                $this->user_capability,
                $this->slug,
                [ $this, 'render_options_page' ],
                $this->menu_position
            );

        } else {
            add_menu_page(
                $this->page_title,
                $this->menu_title,
                $this->user_capability,
                $this->slug,
                [ $this, 'render_options_page' ],
                $this->icon_url,
                $this->menu_position
            );

        }
    }

    /**
     * Register the settings.
     */
    public function register_settings() {
        register_setting( $this->option_group_name, $this->option_name, [
            'sanitize_callback' => [ $this, 'sanitize_fields' ],
            'default'           => $this->get_defaults(),
        ] );

        add_settings_section(
            $this->option_name . '_sections',
            false,
            false,
            $this->option_name
        );

        foreach ( $this->settings as $key => $args ) {
            $type = $args['type'] ?? 'text';
            $callback = "render_{$type}_field";
            if ( method_exists( $this, $callback ) ) {
                $tr_class = '';
                if ( array_key_exists( 'tab', $args ) ) {
                    $tr_class .= 'mos-tab-item mos-tab-item--' . sanitize_html_class( $args['tab'] );
                }
                add_settings_field(
                    $key,
                    $args['label'],
                    [ $this, $callback ],
                    $this->option_name,
                    $this->option_name . '_sections',
                    [
                        'label_for' => $key,
                        'class'     => $tr_class
                    ]
                );
            }
        }
    }

    /**
     * Saves our fields.
     */
    public function sanitize_fields( $value ) {
        $value = (array) $value;
        $new_value = [];
        foreach ( $this->settings as $key => $args ) {
            $field_type = $args['type'];
            $new_option_value = $value[$key] ?? '';
            if ( $new_option_value ) {
                $sanitize_callback = $args['sanitize_callback'] ?? $this->get_sanitize_callback_by_type( $field_type );
                $new_value[$key] = call_user_func( $sanitize_callback, $new_option_value, $args );
            } elseif ( 'checkbox' === $field_type ) {
                $new_value[$key] = 0;
            }
        }
        return $new_value;
    }

    /**
     * Returns sanitize callback based on field type.
     */
    protected function get_sanitize_callback_by_type( $field_type ) {
        switch ( $field_type ) {
            case 'select':
                return [ $this, 'sanitize_select_field' ];
                break;
            case 'textarea':
                return 'wp_kses_post';
                break;
            case 'checkbox':
                return [ $this, 'sanitize_checkbox_field' ];
                break;
            case 'repeater':
                return [ $this, 'sanitize_repeater_field' ];
                break;
            case 'text':
                return 'sanitize_text_field';
                break;
            default:
                return [ $this, 'sanitize_default_field' ];
        }
    }

    /**
     * Returns default values.
     */
    protected function get_defaults() {
        $defaults = [];
        foreach ( $this->settings as $key => $args ) {
            $defaults[$key] = $args['default'] ?? '';
        }
        return $defaults;
    }

    /**
     * Sanitizes the checkbox field.
     */
    protected function sanitize_checkbox_field( $value = '', $field_args = [] ) {
        return ( 'on' === $value ) ? 1 : 0;
    }

    /**
     * Sanitizes the select field.
     */
    protected function sanitize_select_field( $value = '', $field_args = [] ) {
        $choices = $field_args['choices'] ?? [];
        if ( array_key_exists( $value, $choices ) ) {
            return $value;
        }
    }

    /**
     * Sanitizes the repeater field.
     */
    protected function sanitize_repeater_field( $value = '', $field_args = [] ) {
        // var_dump($value);
        // die();
        if(@$value && is_array($value) && !end($value)) {
            array_pop($value);
        }
        return $value;
    }

    /**
     * Sanitizes the default field.
     */
    protected function sanitize_default_field( $value = '', $field_args = [] ) {
        return $value;
    }

    /**
     * Renders the options page.
     */
    public function render_options_page() {
        if ( ! current_user_can( $this->user_capability ) ) {
            return;
        }

        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error(
            $this->option_name . '_mesages',
            $this->option_name . '_message',
            esc_html__( 'Settings Saved', 'text_domain' ),
            'updated'
            );
        }

        settings_errors( $this->option_name . '_mesages' );

        ?>
        <div class="mos-theme-option-dashboard-wrapper">
            <div class="mos-theme-option-content">
                <?php $this->render_tabs(); ?>
                <form action="options.php" method="post" class="mos-options-form">
                    <?php
                        settings_fields( $this->option_group_name );
                        do_settings_sections( $this->option_name );
                        submit_button( 'Save Settings' );
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Renders options page tabs.
     */
    protected function render_tabs() {
        if ( empty( $this->args['tabs'] ) ) {
            return;
        }

        $tabs = $this->args['tabs'];
        ?>

        <ul class="mos-tabs">
            <?php
            $n = 0;
            foreach ( $tabs as $id => $label ) :?>
                <li>
                    <a href="#" data-tab="<?php echo esc_attr( $id ); ?>" class="mos-nav-tab<?php echo ( !$n ) ? ' nav-tab-active' : ''; ?> <?php echo esc_attr( $id ); ?>"><?php echo ucfirst( $label ); ?></a>
                    <?php $this->render_subtabs($id)?>
                </li>
                <?php $n++; ?>
            <?php endforeach;?>
        </ul>

        <?php
    }

    protected function render_subtabs($id){
        $subtabs = $this->args['subtabs'];
        if ( empty( $subtabs[$id] ) ) {
            return;
        } else {
            //var_dump($subtabs[$id]);
            echo '<ul>';
            foreach ( $subtabs[$id] as $id => $label ) : ?>
                <li>
                    <a href="#" data-tab="<?php echo esc_attr( $id ); ?>" class="mos-nav-tab <?php echo esc_attr( $id ); ?>"><?php echo ucfirst( $label ); ?></a>                
                </li>
            <?php endforeach;
            echo '</ul>';
        }
    }

    /**
     * Returns an option value.
     */
    protected function get_option_value( $option_name ) {
        $option = get_option( $this->option_name );
        if ( ! array_key_exists( $option_name, $option ) ) {
            return array_key_exists( 'default', $this->settings[$option_name] ) ? $this->settings[$option_name]['default'] : '';
        }
        return $option[$option_name];
    }

    /**
     * Renders a repeater field.
     */
    public function render_repeater_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        //var_dump($value);
        ?>
            <div class="repeater-wrapper">
                <div class="repeater-data">
                    <?php if (@$value && is_array($value)) { ?>
                        <?php foreach($value as $val) { ?>
                            <?php $n = 0;?>
                            <div class="repeater-unit">
                                <input
                                    type="text"
                                    id="<?php echo esc_attr( $args['label_for'] ); ?>"
                                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][]"
                                    value="<?php echo esc_attr( $val ); ?>">
                                <?php if ($n!=0) ?><span class="theme_option_repeater_remove_button button button-secondary">x</span>
                            </div>
                            <?php $n++?>
                        <?php } ?>
                    <?php } else  { ?>
                        <div class="repeater-unit">
                            <input
                                type="text"
                                id="<?php echo esc_attr( $args['label_for'] ); ?>"
                                name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][]"
                                value="">
                        </div>
                    <?php }?>
                </div>
                <span class="theme_option_repeater_add_button button button-secondary">Add Row</span>
                <div class="repeater-data-wrapper" style="display: none">
                    <div class="repeater-unit">
                        <input
                            type="text"
                            name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][]"
                            value=""><span class="theme_option_repeater_remove_button button button-secondary">x</span>
                    </div>
                </div>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
            </div>
        <?php
    }

    /**
     * Renders a text field.
     */
    public function render_text_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <input
                type="text"
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
                name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
                value="<?php echo esc_attr( $value ); ?>">
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }

    /**
     * Renders a datalist field.
     */
    public function render_datalist_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $choices     = $this->settings[$option_name]['choices'] ?? [];
        $description = $this->settings[$option_name]['description'] ?? '';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <input
                list="<?php echo esc_attr( $args['label_for'] ); ?>-list"
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
                name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
                value="<?php echo esc_attr( $value ); ?>">
                <datalist id="<?php echo esc_attr( $args['label_for'] ); ?>-list">                
                    <?php foreach ( $choices as $label ) { ?>
                        <option value="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php } ?>
                </datalist>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }

    /**
     * Renders a color field.
     */
    public function render_color_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        $output     = $this->settings[$option_name]['output'] ?? '';
        $mode     = $this->settings[$option_name]['mode'] ?? '';
        ?>
            <input
                type="color"
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
                name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
                value="<?php echo esc_attr( $value ); ?>">
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>            
            <?php   
            if ( $output ) { 
                $data = get_option($this->option_name.'-css-output');
                if(@$data && is_array($data))
                    $data = array_merge($data,[$args['label_for'] => [$output=> ['data' => $value, 'mood' => $mode, 'type'=>'color']]]);
                else 
                    $data = [$args['label_for'] => [$output=> ['data' => $value, 'mood' => $mode, 'type'=>'color']]];
                //$save_data = [$old_data, $output => [$args['label_for']=> ['data' => $values, 'mood' => $mode, 'type'=>'spacing']]];
                update_option( $this->option_name.'-css-output', $data );
            }
            ?>
        <?php
    }

    /**
     * Renders a link_color field.
     */
    public function render_link_color_field( $args ) {
        $option_name = $args['label_for'];
        $values       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $defaults     = $this->settings[$option_name]['defaults'] ?? '';
        $values       = ($values)?$values:$defaults;
        $options     = ($this->settings[$option_name]['options']) ? $this->settings[$option_name]['options']:['base'];
        $output     = $this->settings[$option_name]['output'] ?? '';
        ?>
            <div class="group-wrap">
                <div class="group-unit">
                    <span class="title"><?php echo __('Regular', 'mos-faqs') ?></span>
                    <input
                    type="color"
                    id="<?php echo esc_attr( $args['label_for'] ); ?>"
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][base]"
                    value="<?php echo esc_attr( @$values['base'] ); ?>">
                </div>
                <?php if (in_array("hover", $options)) : ?>
                <div class="group-unit">
                    <span class="title"><?php echo __('Hover', 'mos-faqs') ?></span>
                    <input
                    type="color"
                    id="<?php echo esc_attr( $args['label_for'] ); ?>-hover"
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][hover]"
                    value="<?php echo esc_attr( @$values['hover'] ); ?>">
                </div>
                <?php endif?>
                <?php if (in_array("active", $options)) : ?>
                <div class="group-unit">
                    <span class="title"><?php echo __('Active', 'mos-faqs') ?></span>
                    <input
                    type="color"
                    id="<?php echo esc_attr( $args['label_for'] ); ?>-active"
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][active]"
                    value="<?php echo esc_attr( @$values['active'] ); ?>">
                </div>
                <?php endif?>
            </div>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
            <?php   
            if ( $output ) { 
                $data = get_option($this->option_name.'-css-output');
                if(@$data && is_array($data))
                    $data = array_merge($data,[$args['label_for'] => [$output=> ['data' => $values, 'mood' => '', 'type'=>'link_color']]]);
                else 
                    $data = [$args['label_for'] => [$output=> ['data' => $values, 'mood' => '', 'type'=>'link_color']]];
                //$save_data = [$old_data, $output => [$args['label_for']=> ['data' => $values, 'mood' => $mode, 'type'=>'spacing']]];
                update_option( $this->option_name.'-css-output', $data );
            }
            ?>
        <?php
    }

    /**
     * Renders a gradient_color field.
     */
    public function render_gradient_color_field( $args ) {
        $option_name = $args['label_for'];
        $values       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $defaults     = $this->settings[$option_name]['defaults'] ?? '';
        $values       = ($values)?$values:$defaults;
        $output     = $this->settings[$option_name]['output'] ?? '';
        $mode     = $this->settings[$option_name]['mode'] ?? '';
        ?>
            <div class="group-wrap">
                <div class="group-unit">
                    <span class="title"><?php echo __('Start Color', 'mos-faqs') ?></span>
                    <input
                    type="color"
                    id="<?php echo esc_attr( $args['label_for'] ); ?>-start"
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][start]"
                    value="<?php echo esc_attr( @$values['start'] ); ?>">
                </div>
                <div class="group-unit">
                    <span class="title"><?php echo __('End Color', 'mos-faqs') ?></span>
                    <input
                    type="color"
                    id="<?php echo esc_attr( $args['label_for'] ); ?>-end"
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][end]"
                    value="<?php echo esc_attr( @$values['end'] ); ?>">
                </div>
                <div class="group-unit">
                    <span class="title"><?php echo __('Type', 'mos-faqs') ?></span>
                    <select
                    id="<?php echo esc_attr( $args['label_for'] ); ?>-type"
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][type]"
                    value="<?php echo esc_attr( @$values['type'] ); ?>">
                        <option value="linear" <?php selected( @$values['type'], 'linear', true ); ?>>Linear</option>
                        <option value="radial" <?php selected( @$values['type'], 'radial', true ); ?>>Radial</option>
                    </select>
                </div>
                <div class="group-unit">
                    <span class="title"><?php echo __('Angle', 'mos-faqs') ?></span>
                    <input
                    type="number"
                    id="<?php echo esc_attr( $args['label_for'] ); ?>-angle"
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][angle]"
                    value="<?php echo esc_attr( @$values['angle'] ); ?>"
                    min="0" 
                    max="360" 
                    step="1">
                </div>
            </div>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
            <?php   
            if ( $output ) { 
                $data = get_option($this->option_name.'-css-output');
                if(@$data && is_array($data))
                    $data = array_merge($data,[$args['label_for'] => [$output=> ['data' => $values, 'mood' => $mode, 'type'=>'gradient_color']]]);
                else 
                    $data = [$args['label_for'] => [$output=> ['data' => $values, 'mood' => $mode, 'type'=>'gradient_color']]];
                //$save_data = [$old_data, $output => [$args['label_for']=> ['data' => $values, 'mood' => $mode, 'type'=>'spacing']]];
                update_option( $this->option_name.'-css-output', $data );
            }
            ?>
        <?php
    }

    /**
     * Renders a date field.
     */
    public function render_date_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <input
                type="date"
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
                name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
                value="<?php echo esc_attr( $value ); ?>">
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }
    

    /**
     * Renders a time field.
     */
    public function render_time_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <input
                type="time"
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
                name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
                value="<?php echo esc_attr( $value ); ?>">
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }

    /**
     * Renders a datetime field.
     */
    public function render_datetime_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <input
                type="datetime-local"
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
                name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
                value="<?php echo esc_attr( $value ); ?>">
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }

    /**
     * Renders a url field.
     */
    public function render_url_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <input
                type="url"
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
                name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
                value="<?php echo esc_attr( $value ); ?>">
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }

    /**
     * Renders a number field.
     */
    public function render_number_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $min = $this->settings[$option_name]['min'] ?? '0';
        $max = $this->settings[$option_name]['max'] ?? '100';
        $step = $this->settings[$option_name]['step'] ?? '1';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <input
                type="number"
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
                name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
                value="<?php echo esc_attr( $value ); ?>"
                min="<?php echo esc_html( $min ); ?>" 
                max="<?php echo esc_html( $max ); ?>" 
                step="<?php echo esc_html( $step ); ?>">
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }

    /**
     * Renders a spacing field.
     */
    public function render_spacing_field( $args ) {
        $option_name = $args['label_for'];
        $values       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $defaults     = $this->settings[$option_name]['defaults'] ?? '';
        $values       = ($values)?$values:$defaults;
        $options     = ($this->settings[$option_name]['options']) ? $this->settings[$option_name]['options']:['top', 'right', 'bottom', 'left'];
        $output     = $this->settings[$option_name]['output'] ?? '';
        $mode     = $this->settings[$option_name]['mode'] ?? '';
        ?>
            <div class="group-wrap">
                <?php if (in_array("top", $options)) : ?>
                <div class="group-unit">                    
                    <span class="title"><?php echo __('Top', 'mos-faqs') ?></span>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][top]"
                        value="<?php echo esc_attr( @$values['top'] ); ?>">
                </div>
                <?php endif?>
                <?php if (in_array("right", $options)) : ?>
                <div class="group-unit">
                    <span class="title"><?php echo __('Right', 'mos-faqs') ?></span>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][right]"
                        value="<?php echo esc_attr( @$values['right'] ); ?>">
                </div>
                <?php endif?>
                <?php if (in_array("bottom", $options)) : ?>
                <div class="group-unit">
                    <span class="title"><?php echo __('Bottom', 'mos-faqs') ?></span>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][bottom]"
                        value="<?php echo esc_attr( @$values['bottom'] ); ?>">
                </div>
                <?php endif?>
                <?php if (in_array("left", $options)) : ?>
                <div class="group-unit">
                    <span class="title"><?php echo __('Left', 'mos-faqs') ?></span>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][left]"
                        value="<?php echo esc_attr( @$values['left'] ); ?>">
                </div>
                <?php endif?>
            </div>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
            <?php   
            if ( $output ) { 
                $data = get_option($this->option_name.'-css-output');
                if(@$data && is_array($data))
                    $data = array_merge($data,[$args['label_for'] => [$output=> ['data' => $values, 'mood' => $mode, 'type'=>'spacing']]]);
                else 
                    $data = [$args['label_for'] => [$output=> ['data' => $values, 'mood' => $mode, 'type'=>'spacing']]];
                //$save_data = [$old_data, $output => [$args['label_for']=> ['data' => $values, 'mood' => $mode, 'type'=>'spacing']]];
                update_option( $this->option_name.'-css-output', $data );
            }
            ?>

        <?php
    }

    /**
     * Renders a border field.
     */
    public function render_border_field( $args ) {
        $option_name = $args['label_for'];
        $values       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $defaults     = $this->settings[$option_name]['defaults'] ?? '';
        $values       = ($values)?$values:$defaults;
        $options     = ($this->settings[$option_name]['options']) ? $this->settings[$option_name]['options']:['top', 'right', 'bottom', 'left'];
        $output     = $this->settings[$option_name]['output'] ?? '';
        $mode     = $this->settings[$option_name]['mode'] ?? '';
        ?>
            <div class="group-wrap">
                <?php if (in_array("top", $options)) : ?>
                <div class="group-unit">                    
                    <span class="title"><?php echo __('Top', 'mos-faqs') ?></span>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][top]"
                        value="<?php echo esc_attr( @$values['top'] ); ?>">
                </div>
                <?php endif?>
                <?php if (in_array("right", $options)) : ?>
                <div class="group-unit">
                    <span class="title"><?php echo __('Right', 'mos-faqs') ?></span>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][right]"
                        value="<?php echo esc_attr( @$values['right'] ); ?>">
                </div>
                <?php endif?>
                <?php if (in_array("bottom", $options)) : ?>
                <div class="group-unit">
                    <span class="title"><?php echo __('Bottom', 'mos-faqs') ?></span>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][bottom]"
                        value="<?php echo esc_attr( @$values['bottom'] ); ?>">
                </div>
                <?php endif?>
                <?php if (in_array("left", $options)) : ?>
                <div class="group-unit">
                    <span class="title"><?php echo __('Left', 'mos-faqs') ?></span>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][left]"
                        value="<?php echo esc_attr( @$values['left'] ); ?>">
                </div>
                <?php endif?>
                <?php if (in_array("style", $options)) : ?>
                <div class="group-unit">
                    <span class="title"><?php echo __('Style', 'mos-faqs') ?></span>
                    <select
                    id="<?php echo esc_attr( $args['label_for'] ); ?>-style"
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][style]">
                        <option value="none " <?php selected( @$values['style'], 'none ', true ); ?>>None</option>
                        <option value="hidden" <?php selected( @$values['style'], 'hidden', true ); ?>>Hidden</option>
                        <option value="dotted" <?php selected( @$values['style'], 'dotted', true ); ?>>Dotted</option>
                        <option value="dashed" <?php selected( @$values['style'], 'dashed', true ); ?>>Dashed</option>
                        <option value="solid" <?php selected( @$values['style'], 'solid', true ); ?>>Solid</option>
                        <option value="double" <?php selected( @$values['style'], 'double', true ); ?>>Double</option>
                        <option value="groove" <?php selected( @$values['style'], 'groove', true ); ?>>Groove</option>
                        <option value="ridge" <?php selected( @$values['style'], 'ridge', true ); ?>>Ridge</option>
                        <option value="inset" <?php selected( @$values['style'], 'inset', true ); ?>>Inset</option>
                        <option value="outset" <?php selected( @$values['style'], 'outset', true ); ?>>Outset</option>
                    </select>
                </div>
                <?php endif?>
                <?php if (in_array("color", $options)) : ?>
                <div class="group-unit">
                    <span class="title"><?php echo __('Color', 'mos-faqs') ?></span>
                    <input
                    type="color"
                    id="<?php echo esc_attr( $args['label_for'] ); ?>-color"
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][color]"
                    value="<?php echo esc_attr( @$values['color'] ); ?>">
                </div>
                <?php endif?>

            </div>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
            <?php   
            if ( $output ) { 
                $data = get_option($this->option_name.'-css-output');
                if(@$data && is_array($data))
                    $data = array_merge($data,[$args['label_for'] => [$output=> ['data' => $values, 'mood' => $mode, 'type'=>'border']]]);
                else 
                    $data = [$args['label_for'] => [$output=> ['data' => $values, 'mood' => $mode, 'type'=>'border']]];
                //$save_data = [$old_data, $output => [$args['label_for']=> ['data' => $values, 'mood' => $mode, 'type'=>'spacing']]];
                update_option( $this->option_name.'-css-output', $data );
            }
            ?>
        <?php
    }

    /**
     * Renders a dimensions field.
     */
    public function render_dimensions_field( $args ) {
        $option_name = $args['label_for'];
        $values       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $defaults     = $this->settings[$option_name]['defaults'] ?? '';
        $values       = ($values)?$values:$defaults;
        $options     = ($this->settings[$option_name]['options']) ? $this->settings[$option_name]['options']:['width', 'height'];
        $output     = $this->settings[$option_name]['output'] ?? '';
        $mode     = $this->settings[$option_name]['mode'] ?? '';
        ?>
            <div class="group-wrap">
                <?php if (in_array("width", $options)) : ?>
                <div class="group-unit">                    
                    <span class="title"><?php echo __('Width', 'mos-faqs') ?></span>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][width]"
                        value="<?php echo esc_attr( @$values['width'] ); ?>">
                </div>
                <?php endif?>
                <?php if (in_array("height", $options)) : ?>
                <div class="group-unit">
                    <span class="title"><?php echo __('Height', 'mos-faqs') ?></span>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][height]"
                        value="<?php echo esc_attr( @$values['height'] ); ?>">
                </div>
                <?php endif?>
            </div>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
            <?php   
            if ( $output ) { 
                $data = get_option($this->option_name.'-css-output');
                if(@$data && is_array($data))
                    $data = array_merge($data,[$args['label_for'] => [$output=> ['data' => $values, 'mood' => $mode, 'type'=>'dimensions']]]);
                else 
                    $data = [$args['label_for'] => [$output=> ['data' => $values, 'mood' => $mode, 'type'=>'dimensions']]];
                //$save_data = [$old_data, $output => [$args['label_for']=> ['data' => $values, 'mood' => $mode, 'type'=>'spacing']]];
                update_option( $this->option_name.'-css-output', $data );
            }
            ?>
        <?php
    }

    /**
     * Renders a background field.
     */
    public function render_background_field( $args ) {
        $option_name = $args['label_for'];
        $values       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $defaults     = $this->settings[$option_name]['defaults'] ?? '';
        $values       = ($values)?$values:$defaults;
        $options     = ($this->settings[$option_name]['options']) ? $this->settings[$option_name]['options']:['color', 'repeat', 'size', 'attachment', 'position', 'image'];
        $output     = $this->settings[$option_name]['output'] ?? '';
        $mode     = $this->settings[$option_name]['mode'] ?? '';
        ?>
            <div class="group-wrap">
                <?php if (in_array("color", $options)) : ?>
                <div class="group-unit">
                    <span class="title"><?php echo __('Background Color', 'mos-faqs') ?></span>
                    <input
                    type="color"
                    id="<?php echo esc_attr( $args['label_for'] ); ?>-color"
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][color]"
                    value="<?php echo esc_attr( @$values['color'] ); ?>">
                </div>
                <?php endif?>
                <?php if (in_array("repeat", $options)) : ?>
                <div class="group-unit">                    
                    <span class="title"><?php echo __('Background repeat', 'mos-faqs') ?></span>
                    <select
                        id="<?php echo esc_attr( $args['label_for'] ); ?>-repeat"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][repeat]"
                    >
                            <option <?php selected( $values['repeat'], 'repeat', true ); ?>>repeat</option>
                            <option <?php selected( $values['repeat'], 'repeat-x', true ); ?>>repeat-x</option>
                            <option <?php selected( $values['repeat'], 'repeat-y', true ); ?>>repeat-y</option>
                            <option <?php selected( $values['repeat'], 'no-repeat', true ); ?>>no-repeat</option>
                            <option <?php selected( $values['repeat'], 'space', true ); ?>>space</option>
                            <option <?php selected( $values['repeat'], 'round', true ); ?>>round</option>
                            <option <?php selected( $values['repeat'], 'initial', true ); ?>>initial</option>
                            <option <?php selected( $values['repeat'], 'inherit', true ); ?>>inherit</option>
                        
                    </select>
                </div>
                <?php endif?>
                <?php if (in_array("size", $options)) : ?>
                <div class="group-unit">                    
                    <span class="title"><?php echo __('Background Size', 'mos-faqs') ?></span>
                    <input
                        list="<?php echo esc_attr( $args['label_for'] ); ?>-size-list"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>-size"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][size]"
                        value="<?php echo esc_attr( $values['size'] ); ?>">
                        <datalist id="<?php echo esc_attr( $args['label_for'] ); ?>-size-list">
                            <option>auto</option>
                            <option>cover</option>
                            <option>contain</option>
                            <option>initial</option>
                            <option>inherit</option>
                        
                    </datalist>
                </div>
                <?php endif?>
                <?php if (in_array("attachment", $options)) : ?>
                <div class="group-unit">                    
                    <span class="title"><?php echo __('Background Attachment', 'mos-faqs') ?></span>
                    <select
                        id="<?php echo esc_attr( $args['label_for'] ); ?>-attachment"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][attachment]"
                    >
                            <option <?php selected( $values['attachment'], 'scroll', true ); ?>>scroll</option>
                            <option <?php selected( $values['attachment'], 'fixed', true ); ?>>fixed</option>
                            <option <?php selected( $values['attachment'], 'local', true ); ?>>local</option>
                            <option <?php selected( $values['attachment'], 'inherit', true ); ?>>inherit</option>
                            <option <?php selected( $values['attachment'], 'initial', true ); ?>>initial</option>
                        
                    </select>
                </div>
                <?php endif?>
                <?php if (in_array("position", $options)) : ?>
                <div class="group-unit">                    
                    <span class="title"><?php echo __('Background Position', 'mos-faqs') ?></span>
                    <input
                        list="<?php echo esc_attr( $args['label_for'] ); ?>-position-list"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>-position"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][position]"
                        value="<?php echo esc_attr( $values['position'] ); ?>">
                        <datalist id="<?php echo esc_attr( $args['label_for'] ); ?>-position-list">
                            <option>left top</option>
                            <option>left center</option>
                            <option>left bottom</option>
                            <option>center top</option>
                            <option>center center</option>
                            <option>center bottom</option>
                            <option>right top</option>
                            <option>right center</option>
                            <option>right bottom</option>                        
                    </datalist>
                </div>
                <?php endif?>
            </div>
            <?php if (in_array("image", $options)) : ?>
                <div class="group-wrap">
                    <div class="photo-container group-unit">
                        <span class="title"><?php echo __('Background Image', 'mos-faqs') ?></span>
                        <input
                            class="photo"
                            type="text"
                            id="<?php echo esc_attr( $args['label_for'] ); ?>-"
                            name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][image]"
                            value="<?php echo esc_attr( @$values['image'] ); ?>">
                        <span class="theme_option_photo_upload_button button button-secondary">Upload image</span>
                        <span class="theme_option_photo_remove_button button button-secondary">Remove image</span>
                        <div class="theme_option_photo_container"><img src="<?php echo ( @$values['image'] )?esc_attr( $values['image'] ):plugins_url( "/images/no_image_available.jpg", __FILE__ ); ?>" data-src="<?php echo plugins_url( "/images/no_image_available.jpg", __FILE__ ); ?>"/></div>
                    </div>
                </div>
            <?php endif?>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
            <?php   
            if ( $output ) { 
                $data = get_option($this->option_name.'-css-output');
                if(@$data && is_array($data))
                    $data = array_merge($data,[$args['label_for'] => [$output=> ['data' => $values, 'mood' => $mode, 'type'=>'background']]]);
                else 
                    $data = [$args['label_for'] => [$output=> ['data' => $values, 'mood' => $mode, 'type'=>'background']]];
                //$save_data = [$old_data, $output => [$args['label_for']=> ['data' => $values, 'mood' => $mode, 'type'=>'spacing']]];
                update_option( $this->option_name.'-css-output', $data );
            }
            ?>
        <?php
    }

    /**
     * Renders a typography field.
     */
    public function render_typography_field( $args ) {
        $option_name = $args['label_for'];
        $values       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $defaults     = $this->settings[$option_name]['defaults'] ?? '';
        $values       = ($values)?$values:$defaults;
        $options     = ($this->settings[$option_name]['options']) ? $this->settings[$option_name]['options']:['family', 'weight', 'alignment', 'size', 'height', 'color'];
        $output      = $this->settings[$option_name]['output'] ?? '';
        $mode      = $this->settings[$option_name]['mode'] ?? '';
        ?>
            <div class="group-wrap">
                <?php if (in_array("family", $options)) : ?>
                <div class="group-unit">                    
                    <span class="title"><?php echo __('Font Family', 'mos-faqs') ?></span>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>-family"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][family]"
                        value="<?php echo esc_attr( @$values['family'] ); ?>">
                </div>
                <?php endif?>
                <?php if (in_array("weight", $options)) : ?>
                <div class="group-unit">                    
                    <span class="title"><?php echo __('Font Weight', 'mos-faqs') ?></span>
                    <select
                        id="<?php echo esc_attr( $args['label_for'] ); ?>-weight"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][weight]"
                    >
                            <option <?php selected( $values['weight'], 100, true ); ?>>100</option>
                            <option <?php selected( $values['weight'], 200, true ); ?>>200</option>
                            <option <?php selected( $values['weight'], 300, true ); ?>>300</option>
                            <option <?php selected( $values['weight'], 400, true ); ?>>400</option>
                            <option <?php selected( $values['weight'], 500, true ); ?>>500</option>
                            <option <?php selected( $values['weight'], 600, true ); ?>>600</option>
                            <option <?php selected( $values['weight'], 700, true ); ?>>700</option>
                            <option <?php selected( $values['weight'], 800, true ); ?>>800</option>
                            <option <?php selected( $values['weight'], 900, true ); ?>>900</option>
                        
                    </select>
                </div>
                <?php endif?>
                <?php if (in_array("alignment", $options)) : ?>
                <div class="group-unit">                    
                    <span class="title"><?php echo __('Text Align', 'mos-faqs') ?></span>
                    <select
                        id="<?php echo esc_attr( $args['label_for'] ); ?>-alignment"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][alignment]"
                    >
                            <option <?php selected( $values['alignment'], 'center', true ); ?>>center</option>
                            <option <?php selected( $values['alignment'], 'left', true ); ?>>left</option>
                            <option <?php selected( $values['alignment'], 'right', true ); ?>>right</option>
                            <option <?php selected( $values['alignment'], 'justify', true ); ?>>justify</option>
                            <option <?php selected( $values['alignment'], 'inherit', true ); ?>>inherit</option>
                            <option <?php selected( $values['alignment'], 'initial', true ); ?>>initial</option>
                        
                    </select>
                </div>
                <?php endif?>
                <?php if (in_array("size", $options)) : ?>
                <div class="group-unit">                    
                    <span class="title"><?php echo __('Font Size', 'mos-faqs') ?></span>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>-size"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][size]"
                        value="<?php echo esc_attr( @$values['size'] ); ?>">
                </div>
                <?php endif?>
                <?php if (in_array("height", $options)) : ?>
                <div class="group-unit">                    
                    <span class="title"><?php echo __('Line Height', 'mos-faqs') ?></span>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $args['label_for'] ); ?>-height"
                        name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][height]"
                        value="<?php echo esc_attr( @$values['height'] ); ?>">
                </div>
                <?php endif?>
                <?php if (in_array("color", $options)) : ?>
                <div class="group-unit">
                    <span class="title"><?php echo __('Font Color', 'mos-faqs') ?></span>
                    <input
                    type="color"
                    id="<?php echo esc_attr( $args['label_for'] ); ?>-color"
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][color]"
                    value="<?php echo esc_attr( @$values['color'] ); ?>">
                </div>
                <?php endif?>
            </div>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
            <?php            
            if ( $output ) { 
                $data = get_option($this->option_name.'-css-output');
                if(@$data && is_array($data))
                    $data = array_merge($data,[$args['label_for'] => [$output=> ['data' => $values, 'mood' => $mode, 'type'=>'typography']]]);
                else 
                    $data = [$args['label_for'] => [$output=> ['data' => $values, 'mood' => $mode, 'type'=>'typography']]];
                //$save_data = [$old_data, $output => [$args['label_for']=> ['data' => $values, 'mood' => $mode, 'type'=>'spacing']]];
                update_option( $this->option_name.'-css-output', $data );
            }
            ?>

        <?php
    }

    /**
     * Renders a range field.
     */
    public function render_range_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $min = $this->settings[$option_name]['min'] ?? '0';
        $max = $this->settings[$option_name]['max'] ?? '100';
        $step = $this->settings[$option_name]['step'] ?? '1';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <div class="range-wrapper">
                <input
                    type="range"
                    id="<?php echo esc_attr( $args['label_for'] ); ?>"
                    class="theme_option_range"
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
                    value="<?php echo esc_attr( $value ); ?>"
                    min="<?php echo esc_html( $min ); ?>" 
                    max="<?php echo esc_html( $max ); ?>" 
                    step="<?php echo esc_html( $step ); ?>">
                <input
                    type="number"
                    id="<?php echo esc_attr( $args['label_for'] ); ?>-input"
                    class="theme_option_range_value"
                    value="<?php echo esc_attr( $value ); ?>"
                    min="<?php echo esc_html( $min ); ?>" 
                    max="<?php echo esc_html( $max ); ?>" 
                    step="<?php echo esc_html( $step ); ?>"
                    readonly
                    >
            </div>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }

    /**
     * Renders a tel field.
     */
    public function render_tel_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <input
                type="tel"
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
                name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
                value="<?php echo esc_attr( $value ); ?>">
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }

    /**
     * Renders a image field.
     */
    public function render_image_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <div class="photo-container">
                <input
                    class="photo"
                    type="text"
                    id="<?php echo esc_attr( $args['label_for'] ); ?>"
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
                    value="<?php echo esc_attr( $value ); ?>">
                <span class="theme_option_photo_upload_button button button-secondary">Upload image</span>
                <span class="theme_option_photo_remove_button button button-secondary">Remove image</span>
                <?php if ( $description ) { ?>
                    <p class="description"><?php echo esc_html( $description ); ?></p>
                <?php } ?>
                <div class="theme_option_photo_container"><img src="<?php echo ( $value )?esc_attr( $value ):plugins_url( "/images/no_image_available.jpg", __FILE__ ); ?>" data-src="<?php echo plugins_url( "/images/no_image_available.jpg", __FILE__ ); ?>"/></div>
            </div>
        <?php
    }
    /**
     * Renders a css field.
     */
    public function render_css_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $rows        = $this->settings[$option_name]['rows'] ?? '4';
        $cols        = $this->settings[$option_name]['cols'] ?? '50';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
        	<textarea 
            class="css-editor"
            id="<?php echo esc_attr( $args['label_for'] ); ?>"
            rows="<?php echo esc_attr( absint( $rows ) ); ?>"
            cols="<?php echo esc_attr( absint( $cols ) ); ?>"
            name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"><?php echo esc_attr( $value ); ?></textarea>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
            
        <?php
    }
    /**
     * Renders a js field.
     */
    public function render_js_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $rows        = $this->settings[$option_name]['rows'] ?? '4';
        $cols        = $this->settings[$option_name]['cols'] ?? '50';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
        	<textarea 
            class="js-editor"
            id="<?php echo esc_attr( $args['label_for'] ); ?>"
            rows="<?php echo esc_attr( absint( $rows ) ); ?>"
            cols="<?php echo esc_attr( absint( $cols ) ); ?>"
            name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"><?php echo esc_attr( $value ); ?></textarea>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
            
        <?php
    }
    /**
     * Renders a textarea field.
     */
    public function render_textarea_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $rows        = $this->settings[$option_name]['rows'] ?? '4';
        $cols        = $this->settings[$option_name]['cols'] ?? '50';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <textarea
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
                rows="<?php echo esc_attr( absint( $rows ) ); ?>"
                cols="<?php echo esc_attr( absint( $cols ) ); ?>"
                name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"><?php echo esc_attr( $value ); ?></textarea>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }

    /**
     * Renders a checkbox field.
     */
    public function render_checkbox_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $text = $this->settings[$option_name]['text'] ?? '';
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <input
                type="checkbox"            
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
                name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
                <?php checked( $value, 1, true ); ?>
            >
            <?php if ( $text ) { ?>
                <label for="<?php echo esc_attr( $args['label_for'] ); ?>"><?php echo esc_html( $text ); ?></label>
            <?php } ?>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }

        /**
     * Renders a group_checkbox field.
     */
    public function render_group_checkbox_field( $args ) {
        $option_name = $args['label_for'];
        $values       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $choices     = $this->settings[$option_name]['choices'] ?? [];
        $defaults     = $this->settings[$option_name]['defaults'] ?? '';
        $values       = ($values)?$values:$defaults;
        ?>
            <div
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
            >
                <?php foreach ( $choices as $choice_v => $label ) {?>
                    <div>
                    <input 
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>][<?php echo $choice_v?>]"
                    value="1" 
                    type="checkbox"            
                    id="<?php echo sanitize_title(esc_attr( $choice_v )); ?>"
                    <?php checked( @$values[$choice_v], 1, true ); ?>>
                    <label for="<?php echo sanitize_title(esc_attr( $choice_v )); ?>"><?php echo esc_html( $label ); ?></label>
                    </div>
                <?php } ?>
            </div>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }

    /**
     * Renders a radio field.
     */
    public function render_radio_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $choices     = $this->settings[$option_name]['choices'] ?? [];
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <div
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
            >
                <?php foreach ( $choices as $choice_v => $label ) { ?>
                    <div>
                    <input 
                    name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
                    value="<?php echo esc_attr( $choice_v ); ?>" 
                    type="radio"            
                    id="<?php echo sanitize_title(esc_attr( $choice_v )); ?>"
                    <?php checked( $choice_v, $value, true ); ?>>
                    <label for="<?php echo sanitize_title(esc_attr( $choice_v )); ?>"><?php echo esc_html( $label ); ?></label>
                    </div>
                <?php } ?>
            </div>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }

    /**
     * Renders a select field.
     */
    public function render_select_field( $args ) {
        $option_name = $args['label_for'];
        $value       = $this->get_option_value( $option_name );
        $description = $this->settings[$option_name]['description'] ?? '';
        $choices     = $this->settings[$option_name]['choices'] ?? [];
        $default     = $this->settings[$option_name]['default'] ?? '';
        $value       = ($value)?$value:$default;
        ?>
            <select
                id="<?php echo esc_attr( $args['label_for'] ); ?>"
                name="<?php echo $this->option_name; ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
            >
                <?php foreach ( $choices as $choice_v => $label ) { ?>
                    <option value="<?php echo esc_attr( $choice_v ); ?>" <?php selected( $choice_v, $value, true ); ?>><?php echo esc_html( $label ); ?></option>
                <?php } ?>
            </select>
            <?php if ( $description ) { ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php } ?>
        <?php
    }

}