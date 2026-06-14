RewriteEngine on
RewriteBase /

{$ssl}

# ============================================
# 旧 URL /fw4/... はすべて /fbp/... へ 301 リダイレクト
# 例: /fw4/app.php?class=login -> /fbp/app.php?class=login
# ============================================
RewriteCond %{REQUEST_URI} ^{$subpath}/fw4/(.*)$ [NC]
RewriteRule ^fw4/(.*)$ {$subpath}/fbp/$1 [R=301,L]


# ============================================
# ここから先は新ディレクトリ fbp 前提の本来のルーティング
# ============================================

# http://domain.com/
RewriteCond %{REQUEST_URI} ^{$subpath}/$
RewriteRule ^$ {$subpath}/fbp/app.php?class={$class}&function={$function} [L]

# http://domain.com/images/sample.png
RewriteCond %{REQUEST_URI} ^{$subpath}/classes/app/images/(.*)$ [NC]
RewriteRule ^ - [L]
RewriteRule ^images/(.*)$ {$subpath}/classes/app/images/$1 [L]
RewriteRule ^fbp/images/(.*)$ {$subpath}/classes/app/images/$1 [L]

# http://domain.com/robots.txt
RewriteCond %{REQUEST_URI} ^{$subpath}/robots.txt$ [NC]
RewriteRule ^ - [L]

# OAuth discovery for MCP clients
RewriteCond %{REQUEST_URI} ^{$subpath}/\.well-known/oauth-protected-resource$ [NC]
RewriteRule ^\.well-known/oauth-protected-resource$ {$subpath}/fbp/app.php?class=mcp_server&function=oauth_protected_resource [L,QSA]
RewriteCond %{REQUEST_URI} ^{$subpath}/\.well-known/oauth-authorization-server$ [NC]
RewriteRule ^\.well-known/oauth-authorization-server$ {$subpath}/fbp/app.php?class=mcp_server&function=oauth_authorization_server [L,QSA]
RewriteCond %{REQUEST_URI} ^{$subpath}/\.well-known/openid-configuration$ [NC]
RewriteRule ^\.well-known/openid-configuration$ {$subpath}/fbp/app.php?class=mcp_server&function=oauth_authorization_server [L,QSA]

# http://domain.com/class_name*function_name
RewriteCond %{REQUEST_URI} ^{$subpath}/(.*)\*(.*)$ [NC]
RewriteRule ^(.*)\*(.*)$ {$subpath}/fbp/app.php?class=$1&function=$2 [L,QSA]

# http://domain.com/class_name*function_name/anything
RewriteCond %{REQUEST_URI} ^{$subpath}/(.*)\*(.*)&(.*)$ [NC]
RewriteRule ^(.*)\*(.*)/(.*)$ {$subpath}/fbp/app.php?class=$1&function=$2&$3 [L,QSA]

# http://domain.com/app/
RewriteCond %{REQUEST_URI} ^{$subpath}/app/(.*)$ [NC]
RewriteRule ^app/(.*)$ {$subpath}/fbp/$1 [L]

# Redirect when no "." is present in the URL (e.g., http://domain.com/customer)
RewriteCond %{REQUEST_URI} !^{$subpath}/fbp/(.*)$ [NC]
RewriteCond %{REQUEST_URI} !\.[^/]+$ [NC]  # Matches URLs without a "." (excluding extensions like .php, .html)
RewriteCond %{REQUEST_URI} ^{$subpath}/(.+)$ [NC]  # Ensures there is a subpath
RewriteRule ^(.+)$ {$subpath}/fbp/app.php?class={$default_class_name}&function=$1 [L]

# All rewrite to http://domain.com/fbp/xxxx
RewriteCond %{REQUEST_URI} !^{$subpath}/fbp/(.*)$ [NC]
RewriteRule ^(.*)$ {$subpath}/fbp/$1 [L]
