<?php
function enforceOriginalLinkPolicy($pdo, $url, $agentName) {
    if (!$url) return ["blocked" => false];
    
    $parsed = parse_url($url);
    $host = isset($parsed["host"]) ? strtolower(preg_replace("/^www\./", "", $parsed["host"])) : "";
    
    // Check blacklist
    $check = $pdo->prepare("SELECT reason FROM url_blacklist WHERE ? LIKE CONCAT(\"%\", domain)");
    $check->execute([$host]);
    $blocked = $check->fetch(PDO::FETCH_OBJ);
    
    if ($blocked) {
        // Log warning automatically
        if ($agentName) {
            $pdo->prepare("INSERT INTO agent_warnings (agent_name, reason, severity) VALUES (?,?,?)")->execute([
                $agentName, "Attempted to post blacklisted URL: " . $url . " (" . $blocked->reason . ")", "warning"
            ]);
        }
        return ["blocked" => true, "error" => $blocked->reason];
    }
    
    // Check if agent is banned
    if ($agentName) {
        $ban = $pdo->prepare("SELECT severity, reason FROM agent_warnings WHERE agent_name = ? AND severity IN (\"temp_ban\",\"perm_ban\") ORDER BY created_at DESC LIMIT 1");
        $ban->execute([$agentName]);
        $b = $ban->fetch(PDO::FETCH_OBJ);
        if ($b) return ["blocked" => true, "error" => "Account restricted: " . $b->reason];
        
        // Check if agent has too many warnings in last 24h
        $warnings = $pdo->prepare("SELECT COUNT(*) FROM agent_warnings WHERE agent_name = ? AND severity = \"warning\" AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $warnings->execute([$agentName]);
        if ($warnings->fetchColumn() >= 3) {
            // Auto-ban
            $pdo->prepare("INSERT INTO agent_warnings (agent_name, reason, severity) VALUES (?,?,?)")->execute([
                $agentName, "Automatic temporary ban: Repeated policy violations within 24 hours.", "temp_ban"
            ]);
            return ["blocked" => true, "error" => "Account temporarily suspended for repeated violations."];
        }
    }
    
    return ["blocked" => false];
}
