<?php
/**
 * Enhanced ID Card Generator v2.0
 * Features: 20 Templates, 3 Shapes, Logo Positioning, Scannable QR/Barcode
 */

/**
 * Get ID card settings for a company
 */
function get_id_card_settings($company_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM id_card_settings WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $settings ?: [
        'company_id' => $company_id,
        'validity_years' => 1,
        'code_type' => 'qr',
        'card_shape' => 'horizontal',
        'template_id' => 1,
        'logo_position' => 'left',
        'primary_color' => '#1e40af',
        'secondary_color' => '#3b82f6',
        'accent_color' => '#f59e0b',
        'text_color' => '#1f2937',
        'show_department' => 1,
        'show_designation' => 1,
        'show_employee_id' => 1,
        'custom_back_text' => 'This card is property of the company. If found, please return.',
        'emergency_contact' => ''
    ];
}

/**
 * Update ID card settings
 */
function update_id_card_settings($company_id, $data) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM id_card_settings WHERE company_id = ?");
    $stmt->execute([$company_id]);
    
    if ($stmt->fetch()) {
        $sql = "UPDATE id_card_settings SET 
            validity_years = ?, code_type = ?, card_shape = ?, template_id = ?, logo_position = ?,
            primary_color = ?, secondary_color = ?, accent_color = ?, text_color = ?,
            show_department = ?, show_designation = ?, show_employee_id = ?,
            custom_back_text = ?, emergency_contact = ?, updated_at = NOW()
            WHERE company_id = ?";
        $params = [
            $data['validity_years'] ?? 1,
            $data['code_type'] ?? 'qr',
            $data['card_shape'] ?? 'horizontal',
            $data['template_id'] ?? 1,
            $data['logo_position'] ?? 'left',
            $data['primary_color'] ?? '#1e40af',
            $data['secondary_color'] ?? '#3b82f6',
            $data['accent_color'] ?? '#f59e0b',
            $data['text_color'] ?? '#1f2937',
            $data['show_department'] ?? 1,
            $data['show_designation'] ?? 1,
            $data['show_employee_id'] ?? 1,
            $data['custom_back_text'] ?? '',
            $data['emergency_contact'] ?? '',
            $company_id
        ];
    } else {
        $sql = "INSERT INTO id_card_settings 
            (company_id, validity_years, code_type, card_shape, template_id, logo_position,
            primary_color, secondary_color, accent_color, text_color,
            show_department, show_designation, show_employee_id, custom_back_text, emergency_contact)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $company_id,
            $data['validity_years'] ?? 1,
            $data['code_type'] ?? 'qr',
            $data['card_shape'] ?? 'horizontal',
            $data['template_id'] ?? 1,
            $data['logo_position'] ?? 'left',
            $data['primary_color'] ?? '#1e40af',
            $data['secondary_color'] ?? '#3b82f6',
            $data['accent_color'] ?? '#f59e0b',
            $data['text_color'] ?? '#1f2937',
            $data['show_department'] ?? 1,
            $data['show_designation'] ?? 1,
            $data['show_employee_id'] ?? 1,
            $data['custom_back_text'] ?? '',
            $data['emergency_contact'] ?? ''
        ];
    }
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Get employee data for ID card
 */
function get_employee_for_id_card($employee_id, $company_id) {
    global $pdo;
    
    $sql = "SELECT e.*, d.name as department_name, c.name as company_name, 
            c.address as company_address, c.email as company_email, c.phone as company_phone,
            c.logo_url as company_logo
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN companies c ON e.company_id = c.id
            WHERE e.id = ? AND e.company_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$employee_id, $company_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get or generate employee verification token
 */
function get_employee_verification_token($employee_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id_card_token FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $result = $stmt->fetch();
    
    if ($result && !empty($result['id_card_token'])) {
        return $result['id_card_token'];
    }
    
    // Generate new token
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("UPDATE employees SET id_card_token = ? WHERE id = ?");
    $stmt->execute([$token, $employee_id]);
    
    return $token;
}

/**
 * Get employee by verification token
 */
function get_employee_by_token($token) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT e.*, c.id as company_id FROM employees e 
                           JOIN companies c ON e.company_id = c.id 
                           WHERE e.id_card_token = ?");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get verification URL for QR code
 */
function get_verification_url($employee_id) {
    $token = get_employee_verification_token($employee_id);
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $base_url = rtrim($base_url, '/');
    
    // Remove /dashboard or /ajax from path
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $path = preg_replace('#/(dashboard|ajax)$#', '', $path);
    
    return $base_url . $path . '/verify_id.php?token=' . $token;
}

/**
 * Generate QR Code URL (uses Google Charts API)
 */
function generate_qr_code($data, $size = 100) {
    return 'https://chart.googleapis.com/chart?cht=qr&chs=' . $size . 'x' . $size . 
           '&chl=' . urlencode($data) . '&choe=UTF-8&chld=M|2';
}

/**
 * Generate Barcode SVG (Code 128 style - simple representation)
 */
function generate_barcode_svg($data, $width = 150, $height = 40) {
    $bars = [];
    $chars = str_split($data);
    
    // Generate pattern based on character values
    foreach ($chars as $char) {
        $val = ord($char);
        for ($i = 0; $i < 4; $i++) {
            $bars[] = ($val >> $i) & 1;
        }
    }
    
    $barCount = count($bars);
    if ($barCount == 0) $barCount = 1;
    $barWidth = $width / $barCount;
    
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">';
    $svg .= '<rect width="100%" height="100%" fill="white"/>';
    
    $x = 0;
    foreach ($bars as $bar) {
        if ($bar) {
            $svg .= '<rect x="'.$x.'" y="0" width="'.$barWidth.'" height="'.$height.'" fill="black"/>';
        }
        $x += $barWidth;
    }
    
    $svg .= '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Get template definitions - 20 unique templates
 */
function get_template_definitions() {
    return [
        // 1-5: Corporate/Professional
        1 => ['name' => 'Corporate Classic', 'category' => 'Corporate', 'style' => 'classic'],
        2 => ['name' => 'Executive Blue', 'category' => 'Corporate', 'style' => 'executive'],
        3 => ['name' => 'Business Pro', 'category' => 'Corporate', 'style' => 'business'],
        4 => ['name' => 'Enterprise', 'category' => 'Corporate', 'style' => 'enterprise'],
        5 => ['name' => 'Official', 'category' => 'Corporate', 'style' => 'official'],
        
        // 6-10: Modern/Minimalist
        6 => ['name' => 'Modern Clean', 'category' => 'Modern', 'style' => 'clean'],
        7 => ['name' => 'Minimal White', 'category' => 'Modern', 'style' => 'minimal_white'],
        8 => ['name' => 'Sleek Dark', 'category' => 'Modern', 'style' => 'sleek_dark'],
        9 => ['name' => 'Flat Design', 'category' => 'Modern', 'style' => 'flat'],
        10 => ['name' => 'Nordic', 'category' => 'Modern', 'style' => 'nordic'],
        
        // 11-15: Creative/Colorful
        11 => ['name' => 'Vibrant Wave', 'category' => 'Creative', 'style' => 'wave'],
        12 => ['name' => 'Gradient Sunset', 'category' => 'Creative', 'style' => 'gradient'],
        13 => ['name' => 'Geometric', 'category' => 'Creative', 'style' => 'geometric'],
        14 => ['name' => 'Abstract', 'category' => 'Creative', 'style' => 'abstract'],
        15 => ['name' => 'Tech Pattern', 'category' => 'Creative', 'style' => 'tech'],
        
        // 16-20: Elegant/Premium
        16 => ['name' => 'Gold Premium', 'category' => 'Elegant', 'style' => 'gold'],
        17 => ['name' => 'Silver Elite', 'category' => 'Elegant', 'style' => 'silver'],
        18 => ['name' => 'Royal Blue', 'category' => 'Elegant', 'style' => 'royal'],
        19 => ['name' => 'Marble Classic', 'category' => 'Elegant', 'style' => 'marble'],
        20 => ['name' => 'Diamond', 'category' => 'Elegant', 'style' => 'diamond']
    ];
}

/**
 * Get card dimensions by shape
 */
function get_card_dimensions($shape) {
    switch ($shape) {
        case 'vertical':
            return ['width' => '53.98mm', 'height' => '85.6mm', 'width_px' => 204, 'height_px' => 324];
        case 'square':
            return ['width' => '70mm', 'height' => '70mm', 'width_px' => 265, 'height_px' => 265];
        case 'horizontal':
        default:
            return ['width' => '85.6mm', 'height' => '53.98mm', 'width_px' => 324, 'height_px' => 204];
    }
}

/**
 * Get template-specific CSS
 */
function get_template_css($template_id, $settings, $shape) {
    $templates = get_template_definitions();
    $template = $templates[$template_id] ?? $templates[1];
    $style = $template['style'];
    
    $primary = $settings['primary_color'] ?? '#1e40af';
    $secondary = $settings['secondary_color'] ?? '#3b82f6';
    $accent = $settings['accent_color'] ?? '#f59e0b';
    $text = $settings['text_color'] ?? '#1f2937';
    
    $css = "";
    
    switch ($style) {
        case 'classic':
            $css = ".card-front { background: linear-gradient(135deg, {$primary} 0%, {$secondary} 100%); }
                    .header-bar { background: rgba(255,255,255,0.15); padding: 8px; }";
            break;
            
        case 'executive':
            $css = ".card-front { background: {$primary}; border-left: 5px solid {$accent}; }
                    .header-bar { border-bottom: 2px solid {$accent}; }";
            break;
            
        case 'business':
            $css = ".card-front { background: #fff; border: 2px solid {$primary}; }
                    .header-bar { background: {$primary}; color: white; }
                    .card-front .name { color: {$primary}; }";
            break;
            
        case 'enterprise':
            $css = ".card-front { background: linear-gradient(180deg, {$primary} 40%, white 40%); }
                    .header-bar { color: white; }";
            break;
            
        case 'official':
            $css = ".card-front { background: white; border: 3px double {$primary}; }
                    .header-bar { background: {$primary}; color: white; padding: 10px; }";
            break;
            
        case 'clean':
            $css = ".card-front { background: #f8fafc; border-bottom: 4px solid {$primary}; }
                    .header-bar { background: transparent; }
                    .card-front .name { color: {$primary}; }";
            break;
            
        case 'minimal_white':
            $css = ".card-front { background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                    .header-bar { background: transparent; }
                    .accent-line { height: 3px; background: {$primary}; position: absolute; bottom: 0; left: 0; right: 0; }";
            break;
            
        case 'sleek_dark':
            $css = ".card-front { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); }
                    .card-front, .card-front * { color: white !important; }
                    .header-bar { border-bottom: 1px solid rgba(255,255,255,0.1); }
                    .accent-dot { width: 8px; height: 8px; background: {$accent}; border-radius: 50%; }";
            break;
            
        case 'flat':
            $css = ".card-front { background: {$primary}; }
                    .card-front, .card-front * { color: white !important; }
                    .photo-frame { border: 3px solid white; }";
            break;
            
        case 'nordic':
            $css = ".card-front { background: #f1f5f9; }
                    .header-bar { background: transparent; }
                    .side-accent { width: 4px; background: {$primary}; position: absolute; left: 0; top: 0; bottom: 0; }";
            break;
            
        case 'wave':
            $css = ".card-front { background: white; overflow: hidden; }
                    .wave-bg { position: absolute; bottom: 0; left: 0; right: 0; height: 40%; 
                               background: linear-gradient(135deg, {$primary} 0%, {$secondary} 100%);
                               clip-path: ellipse(80% 100% at 50% 100%); }";
            break;
            
        case 'gradient':
            $css = ".card-front { background: linear-gradient(135deg, {$primary} 0%, {$secondary} 50%, {$accent} 100%); }
                    .card-front, .card-front * { color: white !important; }";
            break;
            
        case 'geometric':
            $css = ".card-front { background: white; }
                    .geo-shape { position: absolute; width: 100px; height: 100px; background: {$primary}; opacity: 0.1; 
                                 transform: rotate(45deg); top: -30px; right: -30px; }
                    .geo-shape-2 { position: absolute; width: 60px; height: 60px; background: {$accent}; opacity: 0.1;
                                   transform: rotate(45deg); bottom: -20px; left: -20px; }";
            break;
            
        case 'abstract':
            $css = ".card-front { background: linear-gradient(135deg, white 60%, {$primary} 60%); }
                    .abstract-circle { position: absolute; width: 80px; height: 80px; border: 3px solid {$accent};
                                       border-radius: 50%; opacity: 0.3; bottom: 10px; left: 10px; }";
            break;
            
        case 'tech':
            $css = ".card-front { background: #0f172a; }
                    .card-front, .card-front * { color: white !important; }
                    .tech-grid { position: absolute; inset: 0; background-image: 
                                 linear-gradient(rgba(59,130,246,0.1) 1px, transparent 1px),
                                 linear-gradient(90deg, rgba(59,130,246,0.1) 1px, transparent 1px);
                                 background-size: 20px 20px; }";
            break;
            
        case 'gold':
            $css = ".card-front { background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); border: 2px solid #d4af37; }
                    .card-front, .card-front * { color: white !important; }
                    .gold-accent { color: #d4af37 !important; }
                    .header-bar { border-bottom: 1px solid #d4af37; }";
            break;
            
        case 'silver':
            $css = ".card-front { background: linear-gradient(135deg, #1a1a1a 0%, #374151 100%); border: 2px solid #c0c0c0; }
                    .card-front, .card-front * { color: white !important; }
                    .silver-accent { color: #c0c0c0 !important; }";
            break;
            
        case 'royal':
            $css = ".card-front { background: linear-gradient(135deg, #1e3a5f 0%, #0d1b2a 100%); }
                    .card-front, .card-front * { color: white !important; }
                    .royal-border { border: 2px solid #ffd700; padding: 5px; }
                    .crown-accent { color: #ffd700 !important; }";
            break;
            
        case 'marble':
            $css = ".card-front { background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 50%, #f5f5f5 100%); 
                                  border: 1px solid #ccc; }
                    .marble-accent { background: linear-gradient(90deg, {$primary}, {$secondary}); 
                                     -webkit-background-clip: text; -webkit-text-fill-color: transparent; }";
            break;
            
        case 'diamond':
            $css = ".card-front { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
                    .card-front, .card-front * { color: white !important; }
                    .diamond-shine { position: absolute; top: 0; left: 0; right: 0; height: 50%;
                                     background: linear-gradient(180deg, rgba(255,255,255,0.2) 0%, transparent 100%); }";
            break;
    }
    
    return $css;
}

/**
 * Generate ID Card Front HTML
 */
function generate_id_card_front_html($employee_id, $company_id, $for_public = false) {
    global $pdo;
    
    $employee = get_employee_for_id_card($employee_id, $company_id);
    if (!$employee) return '<div class="error">Employee not found</div>';
    
    $settings = get_id_card_settings($company_id);
    $shape = $settings['card_shape'] ?? 'horizontal';
    $template_id = $settings['template_id'] ?? 1;
    $dims = get_card_dimensions($shape);
    $templates = get_template_definitions();
    $template = $templates[$template_id] ?? $templates[1];
    
    // Photo
    $photo_path = '';
    if (!empty($employee['photo_path'])) {
        $photo_file = $for_public ? 'uploads/photos/' . $employee['photo_path'] : '../uploads/photos/' . $employee['photo_path'];
        $photo_path = $photo_file;
    }
    
    // Logo
    $logo_html = '';
    if (!empty($employee['company_logo'])) {
        $logo_file = $for_public ? 'uploads/logos/' . $employee['company_logo'] : '../uploads/logos/' . $employee['company_logo'];
        $logo_html = '<img src="' . htmlspecialchars($logo_file) . '" alt="Logo" class="company-logo">';
    }
    
    // QR/Barcode
    $code_html = '';
    $verification_url = get_verification_url($employee_id);
    
    if ($settings['code_type'] === 'qr') {
        $qr_size = $shape === 'horizontal' ? 60 : 80;
        $qr_url = generate_qr_code($verification_url, $qr_size);
        $code_html = '<img src="' . $qr_url . '" alt="QR" class="qr-code">';
    } elseif ($settings['code_type'] === 'barcode') {
        $barcode = generate_barcode_svg($employee['id_card_token'] ?? '', 100, 30);
        $code_html = '<img src="' . $barcode . '" alt="Barcode" class="barcode">';
    }
    
    // Build layout based on shape
    $is_vertical = ($shape === 'vertical');
    $is_square = ($shape === 'square');
    
    $name = htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']);
    $emp_id = $settings['show_employee_id'] ? htmlspecialchars($employee['payroll_id'] ?? 'N/A') : '';
    $dept = $settings['show_department'] ? htmlspecialchars($employee['department_name'] ?? 'N/A') : '';
    $title = $settings['show_designation'] ? htmlspecialchars($employee['job_title'] ?? 'N/A') : '';
    $company_name = htmlspecialchars($employee['company_name'] ?? 'Company');
    
    // Logo positioning
    $logo_pos = $settings['logo_position'] ?? 'left';
    $logo_align_class = "logo-{$logo_pos}";
    
    $html = '<div class="card-front" style="width: '.$dims['width'].'; height: '.$dims['height'].';">';
    
    // Template decorations
    $style = $template['style'];
    if ($style === 'wave') $html .= '<div class="wave-bg"></div>';
    if ($style === 'geometric') $html .= '<div class="geo-shape"></div><div class="geo-shape-2"></div>';
    if ($style === 'abstract') $html .= '<div class="abstract-circle"></div>';
    if ($style === 'tech') $html .= '<div class="tech-grid"></div>';
    if ($style === 'minimal_white') $html .= '<div class="accent-line"></div>';
    if ($style === 'nordic') $html .= '<div class="side-accent"></div>';
    if ($style === 'diamond') $html .= '<div class="diamond-shine"></div>';
    
    // Content wrapper
    $html .= '<div class="card-content">';
    
    // Header with logo and company
    $html .= '<div class="header-bar ' . $logo_align_class . '">';
    $html .= $logo_html;
    $html .= '<span class="company-name">' . $company_name . '</span>';
    $html .= '</div>';
    
    // Main content area
    if ($is_vertical) {
        // Vertical layout: photo on top, info below
        $html .= '<div class="main-content vertical">';
        $html .= '<div class="photo-section">';
        if ($photo_path) {
            $html .= '<div class="photo-frame"><img src="' . $photo_path . '" alt="Photo" class="employee-photo"></div>';
        } else {
            $html .= '<div class="photo-frame photo-placeholder">' . strtoupper(substr($employee['first_name'],0,1).substr($employee['last_name'],0,1)) . '</div>';
        }
        $html .= '</div>';
        $html .= '<div class="info-section">';
        $html .= '<div class="name">' . $name . '</div>';
        if ($emp_id) $html .= '<div class="emp-id">ID: ' . $emp_id . '</div>';
        if ($title) $html .= '<div class="title">' . $title . '</div>';
        if ($dept) $html .= '<div class="dept">' . $dept . '</div>';
        $html .= '</div>';
        $html .= '<div class="code-section">' . $code_html . '</div>';
        $html .= '</div>';
    } elseif ($is_square) {
        // Square layout: centered
        $html .= '<div class="main-content square">';
        $html .= '<div class="photo-section">';
        if ($photo_path) {
            $html .= '<div class="photo-frame"><img src="' . $photo_path . '" alt="Photo" class="employee-photo"></div>';
        } else {
            $html .= '<div class="photo-frame photo-placeholder">' . strtoupper(substr($employee['first_name'],0,1).substr($employee['last_name'],0,1)) . '</div>';
        }
        $html .= '</div>';
        $html .= '<div class="info-section">';
        $html .= '<div class="name">' . $name . '</div>';
        if ($emp_id) $html .= '<div class="emp-id">ID: ' . $emp_id . '</div>';
        if ($title) $html .= '<div class="title">' . $title . '</div>';
        if ($dept) $html .= '<div class="dept">' . $dept . '</div>';
        $html .= '</div>';
        $html .= '<div class="code-section">' . $code_html . '</div>';
        $html .= '</div>';
    } else {
        // Horizontal layout: photo left, info right
        $html .= '<div class="main-content horizontal">';
        $html .= '<div class="photo-section">';
        if ($photo_path) {
            $html .= '<div class="photo-frame"><img src="' . $photo_path . '" alt="Photo" class="employee-photo"></div>';
        } else {
            $html .= '<div class="photo-frame photo-placeholder">' . strtoupper(substr($employee['first_name'],0,1).substr($employee['last_name'],0,1)) . '</div>';
        }
        $html .= '</div>';
        $html .= '<div class="info-section">';
        $html .= '<div class="name">' . $name . '</div>';
        if ($emp_id) $html .= '<div class="emp-id">ID: ' . $emp_id . '</div>';
        if ($title) $html .= '<div class="title">' . $title . '</div>';
        if ($dept) $html .= '<div class="dept">' . $dept . '</div>';
        $html .= '</div>';
        $html .= '<div class="code-section">' . $code_html . '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div>'; // card-content
    $html .= '</div>'; // card-front
    
    return $html;
}

/**
 * Generate ID Card Back HTML
 */
function generate_id_card_back_html($employee_id, $company_id, $for_public = false) {
    global $pdo;
    
    $employee = get_employee_for_id_card($employee_id, $company_id);
    if (!$employee) return '<div class="error">Employee not found</div>';
    
    $settings = get_id_card_settings($company_id);
    $shape = $settings['card_shape'] ?? 'horizontal';
    $dims = get_card_dimensions($shape);
    
    $issue_date = date('M Y');
    $validity_years = $settings['validity_years'] ?? 1;
    $expiry_date = date('M Y', strtotime("+{$validity_years} years"));
    
    $primary = $settings['primary_color'] ?? '#1e40af';
    
    $html = '<div class="card-back" style="width: '.$dims['width'].'; height: '.$dims['height'].'; background: white; border: 1px solid #ddd;">';
    $html .= '<div class="back-content">';
    
    // Company Info
    $html .= '<div class="back-header" style="background: ' . $primary . '; color: white; padding: 8px; text-align: center;">';
    $html .= '<strong>' . htmlspecialchars($employee['company_name'] ?? 'Company') . '</strong>';
    $html .= '</div>';
    
    // Address
    $html .= '<div class="back-address" style="font-size: 8px; text-align: center; padding: 5px; color: #666;">';
    $html .= htmlspecialchars($employee['company_address'] ?? '');
    if ($employee['company_phone']) {
        $html .= '<br>Tel: ' . htmlspecialchars($employee['company_phone']);
    }
    if ($employee['company_email']) {
        $html .= '<br>' . htmlspecialchars($employee['company_email']);
    }
    $html .= '</div>';
    
    // Validity
    $html .= '<div class="validity" style="text-align: center; padding: 8px; border-top: 1px solid #eee;">';
    $html .= '<div style="font-size: 7px; color: #888;">VALID FROM</div>';
    $html .= '<div style="font-size: 10px; font-weight: bold; color: #333;">' . $issue_date . ' - ' . $expiry_date . '</div>';
    $html .= '</div>';
    
    // Emergency Contact
    if (!empty($settings['emergency_contact'])) {
        $html .= '<div style="font-size: 7px; text-align: center; color: #666; padding: 5px;">';
        $html .= 'Emergency: ' . htmlspecialchars($settings['emergency_contact']);
        $html .= '</div>';
    }
    
    // Custom text
    if (!empty($settings['custom_back_text'])) {
        $html .= '<div style="font-size: 6px; text-align: center; color: #999; padding: 5px; border-top: 1px solid #eee;">';
        $html .= htmlspecialchars($settings['custom_back_text']);
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Get complete CSS for ID cards
 */
function get_id_card_css($settings = []) {
    $template_id = $settings['template_id'] ?? 1;
    $shape = $settings['card_shape'] ?? 'horizontal';
    $dims = get_card_dimensions($shape);
    
    $template_css = get_template_css($template_id, $settings, $shape);
    
    $photo_size = $shape === 'horizontal' ? '55px' : '70px';
    
    $css = "
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: #f1f5f9; padding: 20px; }
    
    .id-card-container { 
        display: flex; 
        gap: 20px; 
        flex-wrap: wrap; 
        justify-content: center; 
        padding: 20px;
    }
    
    .card-front, .card-back {
        border-radius: 10px;
        overflow: hidden;
        position: relative;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        font-size: 10px;
    }
    
    .card-content {
        position: relative;
        z-index: 1;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .header-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
    }
    
    .header-bar.logo-left { justify-content: flex-start; }
    .header-bar.logo-center { justify-content: center; flex-direction: column; text-align: center; }
    .header-bar.logo-right { flex-direction: row-reverse; }
    
    .company-logo {
        height: 25px;
        width: auto;
        max-width: 60px;
        object-fit: contain;
    }
    
    .company-name {
        font-weight: 700;
        font-size: 10px;
    }
    
    .main-content {
        flex: 1;
        padding: 8px;
    }
    
    .main-content.horizontal {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 10px;
        align-items: center;
    }
    
    .main-content.vertical {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 5px;
    }
    
    .main-content.square {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        justify-content: center;
        gap: 5px;
    }
    
    .photo-frame {
        width: {$photo_size};
        height: {$photo_size};
        border-radius: 6px;
        overflow: hidden;
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .employee-photo {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .photo-placeholder {
        font-size: 18px;
        font-weight: bold;
        color: #64748b;
    }
    
    .info-section {
        overflow: hidden;
    }
    
    .name {
        font-size: 12px;
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 2px;
    }
    
    .emp-id, .title, .dept {
        font-size: 8px;
        color: inherit;
        opacity: 0.8;
        line-height: 1.3;
    }
    
    .code-section {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .qr-code, .barcode {
        display: block;
    }
    
    .back-content {
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    /* Template-specific styles */
    {$template_css}
    
    @media print {
        body { background: white; padding: 0; }
        .card-front, .card-back { box-shadow: none; }
        @page { size: auto; margin: 10mm; }
    }
    ";
    
    return $css;
}

/**
 * Generate complete ID card preview page
 */
function generate_id_card_preview_html($employee_id, $company_id, $for_public = false) {
    $settings = get_id_card_settings($company_id);
    $front = generate_id_card_front_html($employee_id, $company_id, $for_public);
    $back = generate_id_card_back_html($employee_id, $company_id, $for_public);
    $css = get_id_card_css($settings);
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee ID Card</title>
    <style>' . $css . '</style>
</head>
<body>
    <div class="id-card-container">
        ' . $front . '
        ' . $back . '
    </div>
    <script>
        // Auto-print for download
        if (window.location.search.includes("print=1")) {
            window.onload = function() { window.print(); };
        }
    </script>
</body>
</html>';
    
    return $html;
}
?>
