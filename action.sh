#!/system/bin/sh

FILE="/sdcard/stopQuiteKill"
SERVICE="/data/adb/modules/QuiteKill/service.sh"
LOG="/data/adb/modules/QuiteKill/logs/toggle.log"
PROP="/data/adb/modules/QuiteKill/module.prop"
WEBROOT="/data/adb/modules/QuiteKill/webroot"

# Logger
log() {
    echo "$(date '+%m-%d %H:%M:%S') - $1" | tee -a "$LOG"
}

# Function to update description in module.prop
update_description() {
    local status="$1"
    # Remove any existing status suffix in description
    sed -i '/^description=/c\description=Kills apps running in background to improve battery & device performance '"$status" "$PROP"
}

# Start PHP web server for app selection interface (runs in background)
if [ -d "$WEBROOT" ] && [ -f "$WEBROOT/api.php" ]; then
    # Create logs directory if it doesn't exist
    mkdir -p /data/adb/modules/QuiteKill/logs
    
    # Start PHP built-in web server on port 8080 in background
    php -S localhost:8080 -t "$WEBROOT" > /dev/null 2>&1 &
    WEB_SERVER_PID=$!
    log "PHP Web Server started with PID $WEB_SERVER_PID"
    
    # Save PHP server PID for later cleanup
    echo "WEB_SERVER_PID=$WEB_SERVER_PID" >> /data/adb/modules/QuiteKill/.config
    
    update_description "(🟢 Enabled) - App Configurator at http://localhost:8080"
    
    # Add KsuWebUI integration entry
    if [ -f /data/adb/ksuweb/config ]; then
        cat >> /data/adb/ksuweb/config << 'EOF'

[QuiteKill]
name=QuiteKill App Selector
url=http://localhost:8080
icon=/system/ui/default/res/drawable/ic_launcher.png
EOF
    fi
else
  touch "$FILE"
  echo "🔴 Power Key function DISABLED"
  log "Press to kill disabled"

  update_description "(🔴 Disabled)"
fi
