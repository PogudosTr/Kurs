# transcribe_local.py

import sys
import whisper
import os
import warnings

warnings.filterwarnings("ignore", category=UserWarning, module="whisper")

if __name__ == "__main__":
    # Проверка, передан ли путь к файлу
    if len(sys.argv) < 2:
        print("Error: No audio file path provided", file=sys.stderr)
        sys.exit(1)

    audio_path = sys.argv[1]
    
    # Убедитесь, что файл существует
    if not os.path.exists(audio_path):
        print(f"Error: Audio file not found at {audio_path}", file=sys.stderr)
        sys.exit(1)

    # --- Настройка модели Whisper ---
    # Выберите размер модели. "base" - хороший баланс между скоростью и точностью.
    # Для лучшей точности используйте "small" или "medium".
    model_name = "base" 
    
    try:
        # Загрузка модели (занимает время при первом запуске)
        model = whisper.load_model(model_name) 
        
        # Транскрибация: указываем русский язык для ускорения и повышения точности
        result = model.transcribe(audio_path, language="ru") 
        
        # Выводим только текст транскрипции в стандартный вывод (stdout)
        print(result["text"])
        
    except Exception as e:
        # Вывод ошибок в стандартный вывод ошибок (stderr)
        print(f"Error during Whisper processing: {e}", file=sys.stderr)
        sys.exit(1)