@echo off
TITLE BlastForge Engine Monitor
COLOR 0A
echo 🚀 BLASTFORGE ENGINE IS STARTING...
echo --------------------------------------------------
echo [INFO] Path: C:\xampp\htdocs\emailblast\worker.php
echo [INFO] Press CTRL+C to stop the engine.
echo --------------------------------------------------
C:\xampp\php\php.exe C:\xampp\htdocs\emailblast\worker.php
pause
