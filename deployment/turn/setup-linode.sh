#!/bin/bash

# --- Server hardening ---
# <UDF name="username" label="Non-root username to create" />
# <UDF name="password" label="Password for non-root user" />
# <UDF name="ssh_key" label="SSH public key for non-root user" />
# <UDF name="fqdn" label="Fully qualified domain name" />
# <UDF name="logwatch_email" label="Email address for logwatch reports" />
# <UDF name="longview" label="Longview URL Key (optional)" default="" />

# --- coturn ---
# <UDF name="auth_secret" label="TURN Auth Secret" example="Generate with: openssl rand -hex 32" />
# <UDF name="realm" label="TURN Realm" default="flashview.io" example="Your app domain, e.g. flashview.io" />
# <UDF name="relay_min_port" label="Relay Port Range - Min" default="49152" example="Lower bound of UDP relay port range" />
# <UDF name="relay_max_port" label="Relay Port Range - Max" default="49252" example="Upper bound of UDP relay port range" />

set -euo pipefail

{

# --- Detect public IP ---
PUBLIC_IP=$(curl -sf https://checkip.amazonaws.com || hostname -I | awk '{print $1}')
echo "==> Public IP: $PUBLIC_IP"

# --- Non-root user ---
echo "==> Creating user $USERNAME..."
id "$USERNAME" &>/dev/null || useradd -m -s /bin/bash "$USERNAME"
echo "$USERNAME:$PASSWORD" | chpasswd
echo "$USERNAME ALL=(ALL) ALL" >> /etc/sudoers
sed -i 's/#force_color_prompt=yes/force_color_prompt=yes/g' /home/"$USERNAME"/.bashrc

mkdir /home/"$USERNAME"/.ssh
chmod 700 /home/"$USERNAME"/.ssh
echo "$SSH_KEY" > /home/"$USERNAME"/.ssh/authorized_keys
chmod 400 /home/"$USERNAME"/.ssh/authorized_keys
chown -R "$USERNAME":"$USERNAME" /home/"$USERNAME"

# --- Hostname ---
echo "$FQDN" > /etc/hostname
hostname "$FQDN"
echo "$PUBLIC_IP $FQDN" >> /etc/hosts

# --- Harden SSH ---
sed -i 's/PermitRootLogin yes/PermitRootLogin no/g' /etc/ssh/sshd_config
sed -i 's/PasswordAuthentication yes/PasswordAuthentication no/g' /etc/ssh/sshd_config
systemctl restart sshd

# --- System updates ---
apt-get update -qq
apt-get upgrade -y -qq

# --- Unattended upgrades ---
apt-get install -y -qq unattended-upgrades
sed -i 's/APT::Periodic::Download-Upgradeable-Packages "0"/APT::Periodic::Download-Upgradeable-Packages "1"/g' /etc/apt/apt.conf.d/10periodic
sed -i 's/APT::Periodic::AutocleanInterval "0"/APT::Periodic::AutocleanInterval "7"/g' /etc/apt/apt.conf.d/10periodic
echo 'APT::Periodic::Unattended-Upgrade "1";' >> /etc/apt/apt.conf.d/10periodic

# --- fail2ban ---
apt-get install -y -qq fail2ban

# --- Longview (optional) ---
if [[ -n "$LONGVIEW" ]]; then
  curl -s "https://lv.linode.com/$LONGVIEW" | bash
  systemctl restart longview
fi

# --- Logwatch on first login ---
cat >> /home/"$USERNAME"/.profile <<EOM

if [ -f /home/${USERNAME}/first.boot ]; then
  sudo apt-get install -y logwatch
  sudo sed -i "s/--output mail/--output mail --mailto ${LOGWATCH_EMAIL} --detail high/g" /etc/cron.daily/00logwatch
  sudo dpkg-reconfigure tzdata
  sudo apt-get update && sudo apt-get upgrade -y
  rm /home/${USERNAME}/first.boot
  sed -i '/first.boot/d' /home/${USERNAME}/.profile
fi
EOM
chown "$USERNAME":"$USERNAME" /home/"$USERNAME"/.profile
touch /home/"$USERNAME"/first.boot
chown "$USERNAME":"$USERNAME" /home/"$USERNAME"/first.boot

# --- coturn ---
echo "==> Installing coturn..."
apt-get install -y -qq coturn

echo "==> Configuring firewall..."
ufw allow 22/tcp
ufw allow 3478/udp
ufw allow 3478/tcp
ufw allow "${RELAY_MIN_PORT}:${RELAY_MAX_PORT}/udp"
ufw --force enable

echo "==> Writing /etc/turnserver.conf..."
cat > /etc/turnserver.conf <<EOF
listening-port=3478
external-ip=${PUBLIC_IP}
realm=${REALM}
use-auth-secret
static-auth-secret=${AUTH_SECRET}
min-port=${RELAY_MIN_PORT}
max-port=${RELAY_MAX_PORT}
log-file=/var/log/turnserver.log
verbose
no-tls
no-dtls
EOF

sed -i 's/#TURNSERVER_ENABLED=1/TURNSERVER_ENABLED=1/' /etc/default/coturn
systemctl enable coturn

echo ""
echo "==> Done. Set these on Laravel Cloud:"
echo "  TURN_HOST=${PUBLIC_IP}"
echo "  TURN_AUTH_SECRET=${AUTH_SECRET}"
echo ""
echo "==> Rebooting..."
reboot

} > /var/log/deploy 2>/var/log/deploy.error
