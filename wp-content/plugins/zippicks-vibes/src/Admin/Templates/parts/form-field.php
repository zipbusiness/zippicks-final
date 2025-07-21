<?php
/**
 * Form Field Template Part
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 * @var array $field_data Field configuration data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access prohibited');
}

// Default field configuration
$defaults = array(
    'type' => 'text',
    'name' => '',
    'id' => '',
    'label' => '',
    'value' => '',
    'placeholder' => '',
    'description' => '',
    'required' => false,
    'readonly' => false,
    'disabled' => false,
    'class' => '',
    'attributes' => array(),
    'options' => array(), // for select, radio, checkbox
    'wrapper_class' => 'form-field',
    'label_class' => '',
    'error' => '',
    'validation' => array()
);

$field = wp_parse_args($field_data ?? array(), $defaults);

// Generate ID if not provided
if (empty($field['id'])) {
    $field['id'] = !empty($field['name']) ? $field['name'] : 'field_' . wp_generate_uuid4();
}

// Wrapper classes
$wrapper_classes = array($field['wrapper_class']);
if ($field['required']) {
    $wrapper_classes[] = 'required';
}
if (!empty($field['error'])) {
    $wrapper_classes[] = 'has-error';
}

// Field attributes
$attributes = array_merge(array(
    'id' => $field['id'],
    'name' => $field['name'],
    'class' => $field['class']
), $field['attributes']);

if ($field['required']) {
    $attributes['required'] = 'required';
    $attributes['aria-required'] = 'true';
}
if ($field['readonly']) {
    $attributes['readonly'] = 'readonly';
}
if ($field['disabled']) {
    $attributes['disabled'] = 'disabled';
}
if (!empty($field['placeholder'])) {
    $attributes['placeholder'] = $field['placeholder'];
}
if (!empty($field['description'])) {
    $attributes['aria-describedby'] = $field['id'] . '-description';
}
if (!empty($field['error'])) {
    $attributes['aria-describedby'] = $field['id'] . '-error';
    $attributes['aria-invalid'] = 'true';
}

// Build attributes string
$attributes_string = '';
foreach ($attributes as $key => $value) {
    if ($value !== '' && $value !== null) {
        $attributes_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
    }
}
?>

<div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>">
    
    <?php if (!empty($field['label'])): ?>
        <label for="<?php echo esc_attr($field['id']); ?>" 
               class="field-label <?php echo esc_attr($field['label_class']); ?>">
            <?php echo esc_html($field['label']); ?>
            <?php if ($field['required']): ?>
                <span class="required-indicator" aria-label="<?php esc_attr_e('required field', 'zippicks-vibes'); ?>">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>
    
    <div class="field-input">
        <?php switch ($field['type']): 
            case 'text':
            case 'email':
            case 'url':
            case 'tel':
            case 'password':
            case 'number':
            case 'date':
            case 'time':
            case 'datetime-local': ?>
                <input type="<?php echo esc_attr($field['type']); ?>" 
                       value="<?php echo esc_attr($field['value']); ?>"
                       <?php echo $attributes_string; ?> />
                <?php break; ?>
                
            <?php case 'textarea': ?>
                <textarea <?php echo $attributes_string; ?>><?php echo esc_textarea($field['value']); ?></textarea>
                <?php break; ?>
                
            <?php case 'select': ?>
                <select <?php echo $attributes_string; ?>>
                    <?php if (!empty($field['placeholder'])): ?>
                        <option value=""><?php echo esc_html($field['placeholder']); ?></option>
                    <?php endif; ?>
                    <?php foreach ($field['options'] as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" 
                                <?php selected($field['value'], $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php break; ?>
                
            <?php case 'checkbox': ?>
                <label class="checkbox-wrapper">
                    <input type="checkbox" 
                           value="1"
                           <?php checked($field['value'], 1); ?>
                           <?php echo $attributes_string; ?> />
                    <span class="checkbox-label"><?php echo esc_html($field['label']); ?></span>
                </label>
                <?php break; ?>
                
            <?php case 'radio': ?>
                <fieldset class="radio-group" role="radiogroup">
                    <?php if (!empty($field['label'])): ?>
                        <legend class="radio-legend"><?php echo esc_html($field['label']); ?></legend>
                    <?php endif; ?>
                    <?php foreach ($field['options'] as $value => $label): ?>
                        <label class="radio-wrapper">
                            <input type="radio" 
                                   name="<?php echo esc_attr($field['name']); ?>"
                                   value="<?php echo esc_attr($value); ?>"
                                   <?php checked($field['value'], $value); ?>
                                   <?php if ($field['required']): ?>aria-required="true"<?php endif; ?> />
                            <span class="radio-label"><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <?php break; ?>
                
            <?php case 'color': ?>
                <div class="color-field">
                    <input type="color" 
                           value="<?php echo esc_attr($field['value']); ?>"
                           <?php echo $attributes_string; ?> />
                    <input type="text" 
                           name="<?php echo esc_attr($field['name']); ?>_text"
                           value="<?php echo esc_attr($field['value']); ?>"
                           pattern="^#[0-9A-Fa-f]{6}$"
                           class="color-text-input" />
                </div>
                <?php break; ?>
                
            <?php case 'file': ?>
                <input type="file" 
                       <?php echo $attributes_string; ?>
                       <?php if (!empty($field['accept'])): ?>
                           accept="<?php echo esc_attr($field['accept']); ?>"
                       <?php endif; ?>
                       <?php if (!empty($field['multiple'])): ?>
                           multiple
                       <?php endif; ?> />
                <?php break; ?>
                
            <?php case 'hidden': ?>
                <input type="hidden" 
                       value="<?php echo esc_attr($field['value']); ?>"
                       <?php echo $attributes_string; ?> />
                <?php break; ?>
                
            <?php default: ?>
                <?php
                // Allow custom field types through action hook
                do_action('zippicks_vibes_render_field_type_' . $field['type'], $field, $attributes_string);
                ?>
                <?php break; ?>
        <?php endswitch; ?>
    </div>
    
    <?php if (!empty($field['description'])): ?>
        <div id="<?php echo esc_attr($field['id']); ?>-description" class="field-description">
            <?php echo wp_kses_post($field['description']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($field['error'])): ?>
        <div id="<?php echo esc_attr($field['id']); ?>-error" 
             class="field-error" 
             role="alert" 
             aria-live="polite">
            <?php echo esc_html($field['error']); ?>
        </div>
    <?php endif; ?>
    
</div>