</div><!-- end modern-main -->

<!-- 全新极简Footer -->
<footer class="modern-footer">
  <div class="modern-footer-inner">
    <div class="footer-line"></div>
    <div class="footer-content">
      <div class="footer-brand">
        <h3><?php echo $conf['title']?></h3>
        <p>MODERN FILE SHARING PLATFORM</p>
      </div>
      <div class="footer-info">
        <p>&copy; <?php echo date('Y')?> <a href="/"><?php echo $conf['title']?></a></p>
        <p>POWERED BY OOCLOUD</p>
      </div>
    </div>
    <?php echo $conf['tongji']?>
  </div>
</footer>

<style>
.modern-footer {
  background: #000000;
  color: #ffffff;
  margin-top: 80px;
  border-top: 4px solid #ffffff;
  position: relative;
  overflow: hidden;
}

.modern-footer::before {
  content: '';
  position: absolute;
  top: -2px;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(90deg, transparent, #ffffff, transparent);
  animation: shine 3s infinite;
}

@keyframes shine {
  0%, 100% { transform: translateX(-100%); }
  50% { transform: translateX(100%); }
}

.modern-footer-inner {
  max-width: 1600px;
  margin: 0 auto;
  padding: 60px 40px 40px;
}

.footer-line {
  width: 100px;
  height: 4px;
  background: #ffffff;
  margin-bottom: 40px;
}

.footer-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 40px;
}

.footer-brand h3 {
  font-size: 32px;
  font-weight: 900;
  letter-spacing: 4px;
  text-transform: uppercase;
  margin: 0 0 10px 0;
  color: #ffffff;
}

.footer-brand p {
  font-size: 11px;
  letter-spacing: 3px;
  color: #666666;
  margin: 0;
  font-weight: 700;
}

.footer-info {
  text-align: right;
}

.footer-info p {
  margin: 0 0 8px 0;
  font-size: 13px;
  letter-spacing: 2px;
  font-weight: 600;
  color: #999999;
}

.footer-info a {
  color: #ffffff;
  text-decoration: none;
  border-bottom: 2px solid transparent;
  transition: all 0.3s;
  font-weight: 700;
}

.footer-info a:hover {
  border-bottom-color: #ffffff;
}

@media (max-width: 768px) {
  .modern-footer-inner {
    padding: 40px 20px 30px;
  }

  .footer-content {
    flex-direction: column;
    gap: 30px;
  }

  .footer-brand h3 {
    font-size: 24px;
    letter-spacing: 3px;
  }

  .footer-brand p {
    font-size: 10px;
    letter-spacing: 2px;
  }

  .footer-info {
    text-align: left;
  }
}
</style>

<script src="https://s4.zstatic.net/ajax/libs/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/js/material.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/js/ripples.min.js"></script>
<script>
  $.material.init();
</script>
