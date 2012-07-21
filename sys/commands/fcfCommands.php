<?php
class FCF_Command_Nuke{
    public static function run(){
        R::nuke();
    }
}
class FCF_Command_Test{
    public static function run(){
        return "the test command ran happily ever after";
    }
}
class FCF_Command_Install{
    public static function run(){
        die("no install command implemented yet");
    }
}
?>
