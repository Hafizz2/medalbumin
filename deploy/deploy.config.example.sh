# Copy to deploy/deploy.config.sh and fill in. deploy.config.sh is gitignored.
SSH_USER="cpanelusername"
SSH_HOST="medalbumin.com"        # or the server hostname from the hosting welcome email
SSH_PORT="22"
SSH_KEY="$HOME/.ssh/id_ed25519"  # path to your private key

# Where things live on the server (defaults match DEPLOY.md).
REMOTE_APP="medalbumin"          # app code → ~/medalbumin
REMOTE_WEB="public_html"         # web root → ~/public_html
