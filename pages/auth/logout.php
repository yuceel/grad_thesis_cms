<?php
session_unset();
session_destroy();
set_message('Başarıyla çıkış yaptınız.', 'success');
redirect('home');
?> 