#!/bin/bash

# ============================================
# ANTI DELETE FILE PROTECTOR
# ============================================

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color

# Animation colors
RAINBOW=($RED $GREEN $YELLOW $BLUE $PURPLE $CYAN)

# Configuration
BACKUP_DIR="/var/tmp/.file_protector_backups"
LOG_FILE="/var/tmp/.file_protector.log"
CONFIG_DIR="/var/tmp/.file_protector"
PID_FILE="$CONFIG_DIR/pids.list"
PROTECTED_FILES="$CONFIG_DIR/protected.list"
LOCK_FILE="/var/tmp/.file_protector.lock"
SCRIPT_DIR="/var/tmp"

# ============================================
# ASCII ART & BANNER
# ============================================

show_banner() {
    clear
    echo -e "${CYAN}"
    echo -e "    ___          __  _    ____       __     __          _____ __         ____"
    echo -e "   /   |  ____  / /_(_)  / __ \___  / /__  / /____     / ___// /_  ___  / / /"
    echo -e "  / /| | / __ \/ __/ /  / / / / _ \/ / _ \/ __/ _ \    \__ \/ __ \/ _ \/ / /"
    echo -e " / ___ |/ / / / /_/ /  / /_/ /  __/ /  __/ /_/  __/   ___/ / / / /  __/ / /"
    echo -e "/_/  |_/_/ /_/\__/_/  /_____/\___/_/\___/\__/\___/   /____/_/ /_/\___/_/_/"
    echo -e "${NC}"
    echo -e "${YELLOW}╔═══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║                     Tools Anti Delete File                        ║${NC}"
    echo -e "${YELLOW}║                       Coded by : HadiGh0sT                        ║${NC}"
    echo -e "${YELLOW}╚═══════════════════════════════════════════════════════════════════╝${NC}"
    echo -e ""
}

# ============================================
# ANIMATION FUNCTIONS
# ============================================

loading_animation() {
    local message="$1"
    echo -ne "${CYAN}[*] ${message} ${NC}"
    for i in {1..3}; do
        for color in "${RAINBOW[@]}"; do
            echo -ne "${color}.${NC}"
            sleep 0.1
        done
    done
    echo -e "${GREEN} ✓${NC}"
}

spinner() {
    local pid=$1
    local delay=0.1
    local spinstr='|/-\'
    while [ "$(ps a | awk '{print $1}' | grep $pid)" ]; do
        local temp=${spinstr#?}
        printf " [%c]  " "$spinstr"
        local spinstr=$temp${spinstr%"$temp"}
        sleep $delay
        printf "\b\b\b\b\b\b"
    done
    printf "    \b\b\b\b"
}

progress_bar() {
    local duration=${1}
    already_done() { for ((done=0; done<$elapsed; done++)); do printf "${GREEN}▓${NC}"; done }
    remaining() { for ((remain=$elapsed; remain<$duration; remain++)); do printf "${WHITE}░${NC}"; done }
    percentage() { printf "${PURPLE}| %s%%${NC}" $(( (($elapsed)*100)/($duration)*100/100 )); }
    clean_line() { printf "\r"; }

    for (( elapsed=1; elapsed<=$duration; elapsed++ )); do
        already_done; remaining; percentage
        sleep 0.1
        clean_line
    done
    printf "\n"
}

# ============================================
# INITIALIZATION FUNCTIONS
# ============================================

init_system() {
    echo -e "${BLUE}[*] Initializing Anti Delete File System...${NC}"
    
    # Create necessary directories
    mkdir -p "$BACKUP_DIR" "$CONFIG_DIR" 2>/dev/null
    
    # Set proper permissions
    chmod 700 "$CONFIG_DIR" 2>/dev/null
    chmod 600 "$LOG_FILE" 2>/dev/null 2>/dev/null
    
    # Initialize files if not exist
    [ ! -f "$PROTECTED_FILES" ] && touch "$PROTECTED_FILES"
    [ ! -f "$PID_FILE" ] && touch "$PID_FILE"
    [ ! -f "$LOG_FILE" ] && touch "$LOG_FILE"
    
    loading_animation "System Initialization"
    return 0
}

# ============================================
# LOGGING FUNCTIONS
# ============================================

log_message() {
    local message="$1"
    local level="${2:-INFO}"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    echo -e "[$timestamp] [$level] $message" >> "$LOG_FILE"
    
    case $level in
        "SUCCESS") echo -e "${GREEN}[+] $message${NC}" ;;
        "ERROR") echo -e "${RED}[-] $message${NC}" ;;
        "WARNING") echo -e "${YELLOW}[!] $message${NC}" ;;
        "INFO") echo -e "${BLUE}[*] $message${NC}" ;;
        *) echo -e "[*] $message" ;;
    esac
}

# ============================================
# BACKUP FUNCTIONS - DIPERBAIKI
# ============================================

create_backup() {
    local file_path="$1"
    local timestamp=$(date '+%Y%m%d_%H%M%S')
    local backup_name="$(basename "$file_path").backup.$timestamp"
    local backup_path="$BACKUP_DIR/$backup_name"
    
    log_message "Creating backup for: $file_path" "INFO"
    
    if [ -f "$file_path" ]; then
        cp -p "$file_path" "$backup_path" 2>/dev/null
        
        # Set backup file permission to 444
        chmod 444 "$backup_path" 2>/dev/null
        
        # VERIFIKASI: Cek backup benar-benar terbuat
        if [ $? -eq 0 ] && [ -f "$backup_path" ]; then
            local original_size=$(stat -c %s "$file_path" 2>/dev/null || echo "0")
            local backup_size=$(stat -c %s "$backup_path" 2>/dev/null || echo "0")
            
            if [ "$original_size" -eq "$backup_size" ]; then
                log_message "Backup verified: $backup_path (Size: ${backup_size} bytes, Perm: 444)" "SUCCESS"
                echo "$backup_path"
            else
                log_message "Backup size mismatch! Original: ${original_size}, Backup: ${backup_size}" "ERROR"
                rm -f "$backup_path" 2>/dev/null
                return 1
            fi
        else
            log_message "Backup creation failed!" "ERROR"
            return 1
        fi
    elif [ -d "$file_path" ]; then
        cp -rp "$file_path" "$backup_path" 2>/dev/null
        if [ $? -eq 0 ]; then
            log_message "Directory backup created: $backup_path" "SUCCESS"
            echo "$backup_path"
        else
            log_message "Directory backup failed!" "ERROR"
            return 1
        fi
    else
        log_message "File not found: $file_path" "ERROR"
        return 1
    fi
}

# ============================================
# PROTECTION METHODS - VERSI DIPERBAIKI
# ============================================

protect_bash_loop() {
    local file_path="$1"
    local backup_path="$2"
    
    # Create unique ID untuk file ini
    local file_id="file_$(echo "$file_path" | md5sum | cut -c1-8)"
    
    # Create multiple backup files
    local initial_backup="$BACKUP_DIR/${file_id}.initial"
    local last_good_backup="$BACKUP_DIR/${file_id}.lastgood"
    local checksum_file="$CONFIG_DIR/${file_id}.checksum"
    
    # 1. Buat initial backup jika belum ada
    if [ ! -f "$initial_backup" ]; then
        cp -p "$file_path" "$initial_backup" 2>/dev/null
        log_message "Created initial backup: $initial_backup" "INFO"
    fi
    
    # 2. Set last good backup (gunakan initial jika belum ada)
    if [ ! -f "$last_good_backup" ]; then
        cp -p "$initial_backup" "$last_good_backup" 2>/dev/null
    fi
    
    # 3. Simpan checksum awal
    if [ -f "$file_path" ]; then
        md5sum "$file_path" 2>/dev/null | cut -d' ' -f1 > "$checksum_file"
    elif [ -f "$last_good_backup" ]; then
        md5sum "$last_good_backup" 2>/dev/null | cut -d' ' -f1 > "$checksum_file"
    fi
    
    # 4. Create protection script dengan LOGIKA BARU
    local script_name="protect_${file_id}.sh"
    local script_path="$CONFIG_DIR/$script_name"
    
    cat > "$script_path" << EOF
#!/bin/bash
# Enhanced Protection Script - Smart Backup Logic

PROTECT_FILE="$file_path"
INITIAL_BACKUP="$initial_backup"
LAST_GOOD_BACKUP="$last_good_backup"
CHECKSUM_FILE="$checksum_file"
LOG_FILE="$LOG_FILE"

echo "\$(date '+%Y-%m-%d %H:%M:%S') [START] Protection started for: \$PROTECT_FILE" >> "\$LOG_FILE"

# Function to verify file was actually created
verify_file_created() {
    local target_file="\$1"
    local attempt=1
    
    while [ \$attempt -le 3 ]; do
        if [ -f "\$target_file" ]; then
            local file_size=\$(stat -c %s "\$target_file" 2>/dev/null || echo "0")
            if [ "\$file_size" -gt 0 ]; then
                echo "\$(date '+%Y-%m-%d %H:%M:%S') [VERIFIED] File exists: \$target_file (\$file_size bytes)" >> "\$LOG_FILE"
                return 0
            fi
        fi
        sleep 1
        ((attempt++))
    done
    
    echo "\$(date '+%Y-%m-%d %H:%M:%S') [ERROR] File not created after 3 attempts: \$target_file" >> "\$LOG_FILE"
    return 1
}

# Function to check if file content changed
check_content_change() {
    if [ ! -f "\$PROTECT_FILE" ] || [ ! -f "\$CHECKSUM_FILE" ]; then
        echo "missing"
        return
    fi
    
    local current_checksum=\$(md5sum "\$PROTECT_FILE" 2>/dev/null | cut -d' ' -f1)
    local stored_checksum=\$(cat "\$CHECKSUM_FILE" 2>/dev/null)
    
    if [ "\$current_checksum" = "\$stored_checksum" ]; then
        echo "same"
    else
        echo "changed"
    fi
}

    # Function to restore file dengan verifikasi
    restore_file_with_verify() {
        local source_backup="\$1"
        local reason="\$2"
        
        echo "\$(date '+%Y-%m-%d %H:%M:%S') [RESTORE] Attempting restore: \$reason" >> "\$LOG_FILE"
        
        # 1. Pastikan directory ada
        local dir_path=\$(dirname "\$PROTECT_FILE")
        if [ ! -d "\$dir_path" ]; then
            mkdir -p "\$dir_path" 2>/dev/null
            echo "\$(date '+%Y-%m-%d %H:%M:%S') [DIRECTORY] Created: \$dir_path" >> "\$LOG_FILE"
        fi
        
        # 2. Copy file
        cp -p "\$source_backup" "\$PROTECT_FILE" 2>&1 >> "\$LOG_FILE"
        
        # 3. Set permission to 444 (read-only for all)
        chmod 444 "\$PROTECT_FILE" 2>/dev/null
        
        # 4. Verifikasi file benar-benar tercreate
        if verify_file_created "\$PROTECT_FILE"; then
            # 5. Update checksum
            md5sum "\$PROTECT_FILE" 2>/dev/null | cut -d' ' -f1 > "\$CHECKSUM_FILE"
            echo "\$(date '+%Y-%m-%d %H:%M:%S') [SUCCESS] File restored successfully (444)" >> "\$LOG_FILE"
            return 0
        else
            echo "\$(date '+%Y-%m-%d %H:%M:%S') [FAILED] Restore failed!" >> "\$LOG_FILE"
            return 1
        fi
    }

# Main protection loop - OPTION A: Block semua perubahan
while true; do
    sleep 7  # Check setiap 7 detik
    
    # SCENARIO 1: FILE DIHAPUS
    if [ ! -e "\$PROTECT_FILE" ]; then
        restore_file_with_verify "\$LAST_GOOD_BACKUP" "file_deleted"
        continue
    fi
    
    # SCENARIO 2: PERMISSION DIUBAH
    if [ -f "\$PROTECT_FILE" ]; then
        local current_perm=\$(stat -c %a "\$PROTECT_FILE" 2>/dev/null)
        if [[ ! "\$current_perm" =~ ^(444|400)$ ]]; then
            echo "\$(date '+%Y-%m-%d %H:%M:%S') [PERMISSION] Fixing: \$current_perm -> 444" >> "\$LOG_FILE"
            chmod 444 "\$PROTECT_FILE" 2>/dev/null
        fi
    fi
    
    # SCENARIO 3: CONTENT DIEDIT - REVERT OTOMATIS
    local change_status=\$(check_content_change)
    if [ "\$change_status" = "changed" ]; then
        restore_file_with_verify "\$LAST_GOOD_BACKUP" "content_modified"
    fi
    
done
EOF
    
    chmod +x "$script_path"
    
    # 5. Start protection process
    nohup bash "$script_path" >/dev/null 2>&1 &
    local pid=$!
    
    # 6. Save to tracking
    echo "$pid:$file_path:$initial_backup:$last_good_backup:bash_loop:$script_path" >> "$PID_FILE"
    echo "$file_path" >> "$PROTECTED_FILES"
    
    log_message "Enhanced Protection Activated for: $file_path (PID: $pid)" "SUCCESS"
    return $pid
}

# Method 2: Cron Protection (Built-in)
protect_cron() {
    local file_path="$1"
    local backup_path="$2"
    
    # Create unique identifier
    local job_id="protect_$(echo "$file_path" | md5sum | cut -c1-6)"
    
    # Create cron job (checks every minute)
    local cron_line="* * * * * if [ ! -e \"$file_path\" ]; then cp -p \"$backup_path\" \"$file_path\" 2>/dev/null; chmod 444 \"$file_path\" 2>/dev/null; echo \"\$(date): Cron restored $file_path (444)\" >> \"$LOG_FILE\"; fi"
    
    # Add to crontab
    (crontab -l 2>/dev/null | grep -v "$job_id"; echo "# $job_id"; echo "$cron_line") | crontab -
    
    # Save to tracking
    echo "cron:$file_path:$backup_path:$job_id" >> "$CONFIG_DIR/cron_jobs.list"
    echo "$file_path" >> "$PROTECTED_FILES"
    
    log_message "Cron Protection Activated for: $file_path" "SUCCESS"
    return 0
}

# Method 3: File Locking (Prevent deletion)
protect_file_lock() {
    local file_path="$1"
    
    # Try to set immutable attribute (requires root)
    if command -v chattr >/dev/null 2>&1 && [ "$EUID" -eq 0 ]; then
        chattr +i "$file_path" 2>/dev/null
        if [ $? -eq 0 ]; then
            log_message "Immutable attribute set for: $file_path" "SUCCESS"
            echo "immutable:$file_path" >> "$CONFIG_DIR/locked_files.list"
            return 0
        fi
    fi
    
    # Alternative: Keep file open
    exec 9>"$file_path"
    echo "file_handle:$file_path" >> "$CONFIG_DIR/locked_files.list"
    log_message "File handle locked for: $file_path" "SUCCESS"
    return 9
}

# ============================================
# VERIFICATION FUNCTION - BARU
# ============================================

verify_protection() {
    local file_path="$1"
    
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                     VERIFY PROTECTION                           ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════════╝${NC}"
    
    echo -e "\n${WHITE}Verifying protection for:${NC} ${CYAN}$file_path${NC}\n"
    
    # 1. Cek file di filesystem
    if [ ! -e "$file_path" ]; then
        echo -e "${RED}✗ File NOT FOUND on filesystem${NC}"
    else
        echo -e "${GREEN}✓ File exists on filesystem${NC}"
        echo -e "  Size: $(stat -c %s "$file_path" 2>/dev/null) bytes"
        echo -e "  Perm: $(stat -c %a "$file_path" 2>/dev/null)"
    fi
    
    # 2. Cek di protected list
    if grep -q "^$file_path$" "$PROTECTED_FILES" 2>/dev/null; then
        echo -e "${GREEN}✓ File in protected list${NC}"
    else
        echo -e "${RED}✗ File NOT in protected list${NC}"
    fi
    
    # 3. Cek process yang running
    local pid_line=$(grep ":$file_path:" "$PID_FILE" 2>/dev/null)
    if [ -n "$pid_line" ]; then
        local pid=$(echo "$pid_line" | cut -d: -f1)
        if ps -p "$pid" >/dev/null 2>&1; then
            echo -e "${GREEN}✓ Protection process running (PID: $pid)${NC}"
        else
            echo -e "${RED}✗ Protection process NOT running (PID: $pid)${NC}"
        fi
    else
        echo -e "${RED}✗ No protection process found${NC}"
    fi
    
    # 4. Cek backup files
    local file_id="file_$(echo "$file_path" | md5sum | cut -c1-8)"
    local initial_backup="$BACKUP_DIR/${file_id}.initial"
    local last_good_backup="$BACKUP_DIR/${file_id}.lastgood"
    
    if [ -f "$initial_backup" ]; then
        echo -e "${GREEN}✓ Initial backup exists: $(basename "$initial_backup")${NC}"
    else
        echo -e "${RED}✗ Initial backup missing${NC}"
    fi
    
    if [ -f "$last_good_backup" ]; then
        echo -e "${GREEN}✓ Last good backup exists: $(basename "$last_good_backup")${NC}"
    else
        echo -e "${RED}✗ Last good backup missing${NC}"
    fi
    
    echo -e "\n${YELLOW}[Press Enter to continue...]${NC}"
    read -r
}

# ============================================
# MONITORING FUNCTIONS - DIPERBAIKI
# ============================================

show_protected_files() {
    echo -e "${CYAN}"
    echo -e "╔══════════════════════════════════════════════════════════════════╗"
    echo -e "║                     PROTECTED FILES LIST                         ║"
    echo -e "╠══════════════════════════════════════════════════════════════════╣${NC}"
    
    if [ ! -s "$PROTECTED_FILES" ]; then
        echo -e "${YELLOW}║                    No files are protected                        ║${NC}"
    else
        local count=1
        while IFS= read -r file; do
            if [ -n "$file" ]; then
                # Cek status file
                if [ -e "$file" ]; then
                    local status="${GREEN}ACTIVE${NC}"
                    local size=$(stat -c %s "$file" 2>/dev/null || echo "N/A")
                    local perm=$(stat -c %a "$file" 2>/dev/null || echo "N/A")
                    
                    # Cek jika ada process yang protect
                    if grep -q ":$file:" "$PID_FILE" 2>/dev/null; then
                        local pid=$(grep ":$file:" "$PID_FILE" | cut -d: -f1)
                        if ps -p "$pid" >/dev/null 2>&1; then
                            local protection="${GREEN}PROTECTED${NC}"
                        else
                            local protection="${RED}DEAD${NC}"
                        fi
                    else
                        local protection="${YELLOW}NO PROCESS${NC}"
                    fi
                    
                    printf "${WHITE}║ %3d. %-40s ${CYAN}%8s ${GREEN}%6s ${BLUE}%10s${NC}\n" \
                        "$count" "$(basename "$file")" "$size" "$status" "$protection"
                else
                    local status="${RED}MISSING${NC}"
                    # Cek apakah masih ada process yang running
                    if grep -q ":$file:" "$PID_FILE" 2>/dev/null; then
                        local protection="${YELLOW}TRYING${NC}"
                    else
                        local protection="${RED}STOPPED${NC}"
                    fi
                    
                    printf "${WHITE}║ %3d. %-40s ${CYAN}%8s ${RED}%6s ${YELLOW}%10s${NC}\n" \
                        "$count" "$(basename "$file")" "N/A" "$status" "$protection"
                fi
                ((count++))
            fi
        done < "$PROTECTED_FILES"
    fi
    
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════════╝${NC}"
}

# ============================================
# MAIN MENU FUNCTIONS
# ============================================

menu_protect_file() {
    echo -e "${CYAN}"
    echo -e "╔══════════════════════════════════════════════════════════════════╗"
    echo -e "║                     PROTECT A FILE                               ║"
    echo -e "╚══════════════════════════════════════════════════════════════════╝${NC}"
    
    echo -e "\n${YELLOW}[?] Enter full path to file/directory:${NC}"
    echo -ne "${WHITE}>>> ${NC}"
    read -r file_path
    
    # Validate path
    if [ ! -e "$file_path" ]; then
        log_message "Path does not exist: $file_path" "ERROR"
        return 1
    fi
    
    # Create backup first
    log_message "Creating backup..." "INFO"
    progress_bar 5
    
    local backup_path=$(create_backup "$file_path")
    if [ $? -ne 0 ]; then
        return 1
    fi
    
    # Choose protection method
    echo -e "\n${CYAN}Select Protection Method:${NC}"
    echo -e "${GREEN}[1]${NC} Bash Infinite Loop (Recommended - Auto Restore)"
    echo -e "${GREEN}[2]${NC} Cron Job (Every Minute)"
    echo -e "${GREEN}[3]${NC} File Locking (Prevent Deletion)"
    echo -e "${GREEN}[4]${NC} All Methods (Maximum Protection)"
    echo -ne "\n${YELLOW}[?] Choose [1-4]: ${NC}"
    read -r method
    
    case $method in
        1)
            loading_animation "Activating Bash Loop Protection"
            protect_bash_loop "$file_path" "$backup_path"
            ;;
        2)
            loading_animation "Setting up Cron Protection"
            protect_cron "$file_path" "$backup_path"
            ;;
        3)
            loading_animation "Applying File Locking"
            protect_file_lock "$file_path"
            ;;
        4)
            loading_animation "Applying Maximum Protection"
            protect_bash_loop "$file_path" "$backup_path"
            sleep 1
            protect_cron "$file_path" "$backup_path"
            sleep 1
            protect_file_lock "$file_path"
            ;;
        *)
            log_message "Invalid selection" "ERROR"
            return 1
            ;;
    esac
    
    echo -e "\n${GREEN}══════════════════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}            FILE PROTECTION ACTIVATED SUCCESSFULLY!               ${NC}"
    echo -e "${GREEN}══════════════════════════════════════════════════════════════════${NC}"
    echo -e "${WHITE}File:    ${CYAN}$file_path${NC}"
    echo -e "${WHITE}Backup:  ${CYAN}$backup_path${NC}"
    echo -e "${WHITE}Logs:    ${CYAN}$LOG_FILE${NC}"
    echo -e "${GREEN}══════════════════════════════════════════════════════════════════${NC}"
    
    sleep 2
}

menu_remove_protection() {
    echo -e "${CYAN}"
    echo -e "╔══════════════════════════════════════════════════════════════════╗"
    echo -e "║                     REMOVE PROTECTION                            ║"
    echo -e "╚══════════════════════════════════════════════════════════════════╝${NC}"
    
    show_protected_files
    
    echo -ne "\n${YELLOW}[?] Enter file number to remove protection (or 0 to cancel): ${NC}"
    read -r file_num
    
    if [ "$file_num" -eq 0 ]; then
        return
    fi
    
    # Get the file path
    local file_path=$(sed -n "${file_num}p" "$PROTECTED_FILES" 2>/dev/null)
    
    if [ -z "$file_path" ]; then
        log_message "Invalid file number" "ERROR"
        return 1
    fi
    
    echo -e "\n${RED}[!] WARNING: Removing protection from:${NC}"
    echo -e "${RED}    $file_path${NC}"
    echo -ne "${YELLOW}[?] Are you sure? (yes/no): ${NC}"
    read -r confirm
    
    if [ "$confirm" != "yes" ]; then
        log_message "Cancelled by user" "INFO"
        return
    fi
    
    loading_animation "Removing Protection"
    
    # Remove from protected files list
    sed -i "\|^$file_path$|d" "$PROTECTED_FILES" 2>/dev/null
    
    # Kill bash loop processes
    local pid_line=$(grep ":$file_path:" "$PID_FILE" 2>/dev/null | head -1)
    if [ -n "$pid_line" ]; then
        local pid=$(echo "$pid_line" | cut -d: -f1)
        kill -9 "$pid" 2>/dev/null
        sed -i "\|^$pid:|d" "$PID_FILE" 2>/dev/null
    fi
    
    # Remove cron jobs
    if [ -f "$CONFIG_DIR/cron_jobs.list" ]; then
        sed -i "\|:$file_path:|d" "$CONFIG_DIR/cron_jobs.list" 2>/dev/null
        # Update crontab
        if [ -f "$CONFIG_DIR/cron_jobs.list" ]; then
            crontab -l 2>/dev/null | grep -v "$(grep ":$file_path:" "$CONFIG_DIR/cron_jobs.list" 2>/dev/null | cut -d: -f4)" | crontab -
        fi
    fi
    
    # Remove immutable attribute
    if command -v chattr >/dev/null 2>&1 && [ "$EUID" -eq 0 ]; then
        chattr -i "$file_path" 2>/dev/null
    fi
    
    log_message "Protection removed from: $file_path" "SUCCESS"
    
    echo -e "\n${GREEN}══════════════════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}            PROTECTION REMOVED SUCCESSFULLY!                    ${NC}"
    echo -e "${GREEN}══════════════════════════════════════════════════════════════════${NC}"
    
    sleep 2
}

menu_view_logs() {
    echo -e "${CYAN}"
    echo -e "╔══════════════════════════════════════════════════════════════════╗"
    echo -e "║                     PROTECTION LOGS                              ║"
    echo -e "╚══════════════════════════════════════════════════════════════════╝${NC}"
    
    if [ ! -f "$LOG_FILE" ] || [ ! -s "$LOG_FILE" ]; then
        echo -e "${YELLOW}No logs found${NC}"
        return
    fi
    
    echo -e "\n${WHITE}Last 20 log entries:${NC}\n"
    tail -20 "$LOG_FILE" | while read -r line; do
        if [[ "$line" == *"[RESTORED]"* ]] || [[ "$line" == *"[SUCCESS]"* ]]; then
            echo -e "${GREEN}$line${NC}"
        elif [[ "$line" == *"[FIXED]"* ]] || [[ "$line" == *"[PERMISSION]"* ]]; then
            echo -e "${YELLOW}$line${NC}"
        elif [[ "$line" == *"[ERROR]"* ]] || [[ "$line" == *"[FAILED]"* ]]; then
            echo -e "${RED}$line${NC}"
        elif [[ "$line" == *"[DEBUG]"* ]] || [[ "$line" == *"[VERIFIED]"* ]]; then
            echo -e "${CYAN}$line${NC}"
        else
            echo -e "${WHITE}$line${NC}"
        fi
    done
    
    echo -e "\n${YELLOW}[Press Enter to continue...]${NC}"
    read -r
}

# ============================================
# NEW MENU: VERIFY PROTECTION
# ============================================

menu_verify_protection() {
    echo -e "${CYAN}"
    echo -e "╔══════════════════════════════════════════════════════════════════╗"
    echo -e "║                     VERIFY PROTECTION                            ║"
    echo -e "╚══════════════════════════════════════════════════════════════════╝${NC}"
    
    show_protected_files
    
    echo -ne "\n${YELLOW}[?] Enter file number to verify (or 0 for all): ${NC}"
    read -r choice
    
    if [ "$choice" -eq 0 ]; then
        # Verify all files
        while IFS= read -r file; do
            if [ -n "$file" ]; then
                verify_protection "$file"
            fi
        done < "$PROTECTED_FILES"
    else
        # Verify specific file
        local file_path=$(sed -n "${choice}p" "$PROTECTED_FILES" 2>/dev/null)
        if [ -z "$file_path" ]; then
            log_message "Invalid file number" "ERROR"
            return 1
        fi
        verify_protection "$file_path"
    fi
}

# ============================================
# MAIN FUNCTION
# ============================================

main_menu() {
    while true; do
        show_banner
        
        echo -e "${CYAN}╔══════════════════════════════════════════════════════════════════╗${NC}"
        echo -e "${CYAN}║                         MAIN MENU                                ║${NC}"
        echo -e "${CYAN}╠══════════════════════════════════════════════════════════════════╣${NC}"
        echo -e "${CYAN}║${GREEN}  [1] ${WHITE}Protect a File/Directory                                    ${CYAN}║${NC}"
        echo -e "${CYAN}║${GREEN}  [2] ${WHITE}Remove Protection from File                                 ${CYAN}║${NC}"
        echo -e "${CYAN}║${GREEN}  [3] ${WHITE}View Protected Files                                        ${CYAN}║${NC}"
        echo -e "${CYAN}║${GREEN}  [4] ${WHITE}View Protection Logs                                        ${CYAN}║${NC}"
        echo -e "${CYAN}║${GREEN}  [5] ${WHITE}Verify Protection Status                                    ${CYAN}║${NC}"
        echo -e "${CYAN}║${GREEN}  [6] ${WHITE}System Status                                               ${CYAN}║${NC}"
        echo -e "${CYAN}║${RED}  [7] ${WHITE}Exit Anti Delete Tool                                       ${CYAN}║${NC}"
        echo -e "${CYAN}╚══════════════════════════════════════════════════════════════════╝${NC}"
        
        echo -ne "\n${YELLOW}[?] Select option [1-7]: ${NC}"
        read -r choice
        
        case $choice in
            1) menu_protect_file ;;
            2) menu_remove_protection ;;
            3) show_protected_files
               echo -ne "\n${YELLOW}[Press Enter to continue...]${NC}"
               read -r ;;
            4) menu_view_logs ;;
            5) menu_verify_protection ;;
            6) 
                echo -e "\n${CYAN}System Status:${NC}"
                echo -e "${WHITE}Protected Files:${NC} $(wc -l < "$PROTECTED_FILES" 2>/dev/null || echo "0")"
                echo -e "${WHITE}Active Processes:${NC} $(wc -l < "$PID_FILE" 2>/dev/null || echo "0")"
                echo -e "${WHITE}Backup Directory:${NC} $BACKUP_DIR"
                echo -e "${WHITE}Backup Files:${NC} $(ls -1 "$BACKUP_DIR" 2>/dev/null | wc -l)"
                echo -e "${WHITE}Log File:${NC} $LOG_FILE"
                echo -e "${WHITE}Log Size:${NC} $(stat -c %s "$LOG_FILE" 2>/dev/null || echo "0") bytes"
                echo -ne "\n${YELLOW}[Press Enter to continue...]${NC}"
                read -r
                ;;
            7) 
                echo -e "\n${RED}Exiting Anti Delete Tool...${NC}"
                exit 0
                ;;
            *) 
                echo -e "${RED}Invalid option!${NC}"
                sleep 1
                ;;
        esac
    done
}

# ============================================
# SCRIPT START
# ============================================

# Cleanup on exit
cleanup() {
    rm -f "$LOCK_FILE" 2>/dev/null
    log_message "Anti Delete Tool stopped" "INFO"
    exit 0
}

trap cleanup EXIT INT TERM

# Check lock file
if [ -f "$LOCK_FILE" ]; then
    echo -e "${RED}Another instance is already running!${NC}"
    echo -e "${YELLOW}If not, delete: $LOCK_FILE${NC}"
    exit 1
fi

# Create lock file
echo $$ > "$LOCK_FILE"

# Initialize system
init_system

# Start main menu
main_menu
