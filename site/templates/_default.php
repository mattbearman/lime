<?require $this->core->template_path.'_includes/header.php'?>

		<div class="hero-unit">
			<h1><?=$this->title?></h1>
			<?if($this->date):?>
			<p><?=date('jS F Y', strtotime($this->date))?></p>
			<?endif?>
		</div>

		<?=$this->content?>
		
		<p><a href="<?=$this->url('/')?>">Take me home!</a></p>
		<hr>

<?require $this->core->template_path.'_includes/footer.php'?>

