#!/bin/bash&#10;php -m | grep mysqli || echo &quot;CRITICAL: mysqli missing&quot;&#10;php -S 0.0.0.0:${PORT:-8080} -t .
